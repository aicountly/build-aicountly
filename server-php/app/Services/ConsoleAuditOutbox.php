<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Build's central audit to Console (console-react-app AUTH_INTEGRATION.md "Central audit"):
 *
 *   POST {CONSOLE_API_URL}/portal/audit
 *   Authorization: Bearer {CONSOLE_API_TOKEN}       (Build's own Console key, capability audit.write)
 *   {"events": [{action, external_id, app_code: "build", entity_type, entity_id, actor_email,
 *                actor_name, occurred_at, ip, user_agent, old_value, new_value, metadata}]}
 *
 * It used to post once to /api/v1/portal/audit — a route Console never had — and only record the
 * failure. Now each event is a `pending` audit_event row in build_console_syncs (the outbox,
 * migration 2026-10-14-000022), sent at once, and retried with back-off by
 * `php spark console:deliver-audit` until Console answers stored / duplicate (delivered),
 * rejected (kept, not retried), or 12 attempts fail (dead). Console de-duplicates on
 * external_id ('build:audit_logs:<id>'), so a retry never doubles an event. Every failure is
 * logged. Nothing here throws to the caller.
 *
 * CONSOLE_API_URL / CONSOLE_API_TOKEN are the ones the role sync already uses;
 * BUILD_CONSOLE_API_BASE_URL / BUILD_CONSOLE_API_TOKEN are read only when those are unset.
 */
class ConsoleAuditOutbox
{
    public const APP_CODE     = 'build';
    public const KIND         = 'audit_event';
    public const PATH         = '/portal/audit';
    public const MAX_ATTEMPTS = 12;
    public const BATCH        = 100;

    private string $baseUrl;
    private string $token;
    private ?BaseConnection $db;

    /** @var callable(string, list<string>, string): array{status:int, body:string|false|null, error?:string} */
    private $transport;

    public function __construct(?callable $transport = null, ?string $baseUrl = null, ?string $token = null, ?BaseConnection $db = null)
    {
        $base  = $baseUrl ?? (trim((string) env('CONSOLE_API_URL', '')) ?: trim((string) env('BUILD_CONSOLE_API_BASE_URL', '')));
        $token = $token ?? (trim((string) env('CONSOLE_API_TOKEN', '')) ?: trim((string) env('BUILD_CONSOLE_API_TOKEN', '')));
        $this->baseUrl   = rtrim($base, '/');
        $this->token     = $token;
        $this->db        = $db;
        $this->transport = $transport ?? [self::class, 'curlPost'];
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    /**
     * Queue one event and try to deliver it now.
     *
     * @param array<string, mixed> $payload the fields AuditService always sent
     */
    public function record(string $type, array $payload, ?string $externalId = null): void
    {
        try {
            $externalId ??= 'build:event:' . bin2hex(random_bytes(12));
            $event = self::toEvent($type, $payload, $externalId);
            $request = ['method' => 'POST', 'path' => self::PATH, 'payload' => ['events' => [$event]]];

            if (! $this->isConfigured()) {
                $this->db()->table('build_console_syncs')->insert([
                    'kind' => self::KIND, 'direction' => 'outbound', 'status' => 'skipped_not_configured',
                    'request' => json_encode($request), 'error' => 'CONSOLE_API_URL or CONSOLE_API_TOKEN empty.',
                    'entity_type' => self::entityType($payload), 'entity_id' => self::numericId($payload['entity_id'] ?? null),
                ]);
                log_message('warning', "Console audit {$type} not sent: CONSOLE_API_URL / CONSOLE_API_TOKEN not set.");

                return;
            }

            $row = $this->db()->query(
                "INSERT INTO build_console_syncs (kind, direction, status, request, entity_type, entity_id, external_id, next_attempt_at, retry_count)
                 VALUES (?, 'outbound', 'pending', CAST(? AS JSONB), ?, ?, ?, NOW(), 0)
                 ON CONFLICT (external_id) WHERE external_id IS NOT NULL DO NOTHING
                 RETURNING id, external_id, request, retry_count",
                [self::KIND, json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                 self::entityType($payload), self::numericId($payload['entity_id'] ?? null), $externalId]
            )->getRowArray();
            if ($row === null) {
                return; // already queued under this external_id
            }

            $this->apply([$row], $this->send([$event]));
        } catch (\Throwable $e) {
            log_message('error', "Console audit {$type} failed: " . $e->getMessage());
        }
    }

    /**
     * Deliver the audit events that are due (php spark console:deliver-audit).
     *
     * @return array{batches:int, delivered:int, rejected:int, retry:int, dead:int}
     */
    public function deliverDue(int $maxBatches = 20): array
    {
        $out = ['batches' => 0, 'delivered' => 0, 'rejected' => 0, 'retry' => 0, 'dead' => 0];
        for ($b = 0; $b < $maxBatches; $b++) {
            $rows = $this->db()->query(
                "UPDATE build_console_syncs SET next_attempt_at = NOW() + INTERVAL '10 minutes'
                  WHERE id IN (SELECT id FROM build_console_syncs
                                WHERE kind = 'audit_event' AND status = 'pending' AND next_attempt_at <= NOW()
                                ORDER BY id LIMIT " . self::BATCH . ' FOR UPDATE SKIP LOCKED)
                  RETURNING id, external_id, request, retry_count'
            )->getResultArray();
            if ($rows === []) {
                break;
            }
            usort($rows, static fn (array $x, array $y): int => (int) $x['id'] <=> (int) $y['id']);
            $events = array_map(static function (array $r): array {
                $req = json_decode((string) $r['request'], true);

                return $req['payload']['events'][0] ?? ['external_id' => $r['external_id']];
            }, $rows);
            $counts = $this->apply($rows, $this->send($events));
            $out['batches']++;
            foreach ($counts as $k => $n) {
                $out[$k] += $n;
            }
            if ($counts['delivered'] + $counts['rejected'] === 0) {
                break; // Console is not taking anything now; the next run retries.
            }
        }

        return $out;
    }

    /**
     * AuditService's (type, payload) as a Console audit event: the fields it always sent are
     * kept — Console's columns where it has one, the rest in metadata.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function toEvent(string $type, array $payload, string $externalId): array
    {
        $action = (string) ($payload['action'] ?? (str_starts_with($type, 'build.') ? substr($type, 6) : $type));
        $mapped = ['action', 'actor_email', 'actor_name', 'entity_type', 'entity_id', 'old_value', 'new_value', 'ip_address', 'user_agent', 'occurred_at'];
        $meta = ['type' => $type, 'source' => defined('BUILD_PORTAL_SOURCE') ? BUILD_PORTAL_SOURCE : 'build.aicountly.org'];
        foreach ($payload as $k => $v) {
            if (! in_array($k, $mapped, true) && $v !== null) {
                $meta[$k] = $v;
            }
        }
        $entityId = $payload['entity_id'] ?? null;

        return [
            'action'      => $action,
            'external_id' => $externalId,
            'app_code'    => self::APP_CODE,
            'entity_type' => self::entityType($payload),
            'entity_id'   => is_int($entityId) || is_string($entityId) ? $entityId : null,
            'actor_email' => isset($payload['actor_email']) ? (string) $payload['actor_email'] : null,
            'actor_name'  => isset($payload['actor_name']) ? (string) $payload['actor_name'] : null,
            'occurred_at' => is_string($payload['occurred_at'] ?? null) ? $payload['occurred_at'] : gmdate('c'),
            'ip'          => isset($payload['ip_address']) ? (string) $payload['ip_address'] : null,
            'user_agent'  => isset($payload['user_agent']) ? (string) $payload['user_agent'] : null,
            'old_value'   => $payload['old_value'] ?? null,
            'new_value'   => $payload['new_value'] ?? null,
            'metadata'    => $meta,
        ];
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return array{status:int, results:list<array<string,mixed>>|null, error:string, body:string|null}
     */
    public function send(array $events): array
    {
        $body = (string) json_encode(['events' => array_values($events)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        try {
            $res = ($this->transport)($this->baseUrl . self::PATH, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'X-Source: ' . (defined('BUILD_PORTAL_SOURCE') ? BUILD_PORTAL_SOURCE : 'build.aicountly.org'),
            ], $body);
        } catch (\Throwable $e) {
            $res = ['status' => 0, 'body' => null, 'error' => $e->getMessage()];
        }
        $status = (int) ($res['status'] ?? 0);
        $raw = is_string($res['body'] ?? null) ? $res['body'] : null;
        $json = $raw !== null ? json_decode($raw, true) : null;
        $results = $status === 200 && is_array($json['data']['results'] ?? null) ? $json['data']['results'] : null;
        $error = (string) ($res['error'] ?? '');
        if ($results === null && $error === '') {
            $error = 'http_status=' . $status . ($raw !== null && $raw !== '' ? ' ' . substr($raw, 0, 200) : '');
        }

        return ['status' => $status, 'results' => $results, 'error' => $error, 'body' => $raw !== null ? substr($raw, 0, 4096) : null];
    }

    /**
     * Record Console's answer on the outbox rows, in the order they were sent.
     *
     * @param list<array<string, mixed>> $rows id, external_id, retry_count
     * @return array{delivered:int, rejected:int, retry:int, dead:int}
     */
    public function apply(array $rows, array $res): array
    {
        $counts = ['delivered' => 0, 'rejected' => 0, 'retry' => 0, 'dead' => 0];
        $byIndex = [];
        foreach ($res['results'] ?? [] as $r) {
            if (isset($r['index'])) {
                $byIndex[(int) $r['index']] = $r;
            }
        }
        $response = json_encode(['http_status' => $res['status'], 'body' => $res['body'] ?? null]);

        foreach (array_values($rows) as $i => $row) {
            $r = $byIndex[$i] ?? null;
            $outcome = is_array($r) ? (string) ($r['status'] ?? '') : '';
            if ($outcome === 'stored' || $outcome === 'duplicate') {
                // Console holds the event now: keep only where it went, not the personal data.
                $this->db()->query(
                    "UPDATE build_console_syncs SET status = 'delivered', retry_count = retry_count + 1, error = NULL,
                            request = CAST(? AS JSONB), response = CAST(? AS JSONB), updated_at = NOW() WHERE id = ?",
                    [json_encode(['method' => 'POST', 'path' => self::PATH, 'external_id' => $row['external_id'], 'console_audit_id' => $r['id'] ?? null]), $response, $row['id']]
                );
                $counts['delivered']++;

                continue;
            }
            if ($outcome === 'rejected') {
                $err = 'rejected: ' . (string) ($r['error'] ?? 'unknown');
                $this->db()->query(
                    "UPDATE build_console_syncs SET status = 'rejected', retry_count = retry_count + 1, error = ?, response = CAST(? AS JSONB), updated_at = NOW() WHERE id = ?",
                    [$err, $response, $row['id']]
                );
                log_message('error', "Console audit {$row['external_id']} {$err} (not retried)");
                $counts['rejected']++;

                continue;
            }

            $attempts = (int) $row['retry_count'] + 1;
            $dead = $attempts >= self::MAX_ATTEMPTS;
            $delay = min(360, 2 ** min($attempts, 9));
            $err = $res['results'] === null ? $res['error'] : 'no result for this event';
            $this->db()->query(
                "UPDATE build_console_syncs SET status = ?, retry_count = ?, error = ?, response = CAST(? AS JSONB),
                        next_attempt_at = NOW() + make_interval(mins => ?), updated_at = NOW() WHERE id = ?",
                [$dead ? 'dead' : 'pending', $attempts, substr($err, 0, 1000), $response, $delay, $row['id']]
            );
            log_message($dead ? 'critical' : 'warning', sprintf('Console audit %s failed (status %d): %s — %s', $row['external_id'], $res['status'], substr($err, 0, 200),
                $dead ? "dead after {$attempts} attempts" : "attempt {$attempts}, retry in {$delay} min"));
            $counts[$dead ? 'dead' : 'retry']++;
        }

        return $counts;
    }

    private function db(): BaseConnection
    {
        return $this->db ??= Database::connect();
    }

    /** @param array<string, mixed> $payload */
    private static function entityType(array $payload): ?string
    {
        return isset($payload['entity_type']) ? substr((string) $payload['entity_type'], 0, 40) : null;
    }

    private static function numericId(mixed $id): ?int
    {
        return (is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0 ? (int) $id : null;
    }

    /** @return array{status:int, body:string|false, error:string} */
    private static function curlPost(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return ['status' => $status, 'body' => $raw, 'error' => $err];
    }
}
