<?php

namespace App\Services;

/**
 * Pulls a staff user's current Build roles from Console:
 *
 *   GET {CONSOLE_API_URL}/portal/controller-apps/user-access?app_code=build&console_user_id=…  (or &email=…)
 *   Authorization: Bearer {CONSOLE_API_TOKEN}       (Console capability `staff.roles`)
 *
 * → {success:true, data:{exists, active, app_access, app_superadmin, console_user_id,
 *                        email, name, app_roles:[…], authz_version}}
 *
 * The lookup is "configured" only when both CONSOLE_API_URL and
 * CONSOLE_API_TOKEN are set. Any transport failure, non-2xx status or
 * malformed body is reported as `unavailable` — never as a revocation —
 * so a Console outage cannot lock everyone out (the caller applies the
 * offline grace window instead).
 */
class ConsoleUserAccessClient
{
    public const STATUS_OK             = 'ok';
    public const STATUS_UNAVAILABLE    = 'unavailable';
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    private const TIMEOUT_SECONDS = 10;

    private string $baseUrl;
    private string $token;
    private string $appCode;

    /** @var callable(string, list<string>): array{status:int, body:string|false} */
    private $transport;

    /**
     * @param (callable(string, list<string>): array{status:int, body:string|false})|null $transport
     *        injectable for tests; defaults to a cURL GET.
     */
    public function __construct(?callable $transport = null, ?string $baseUrl = null, ?string $token = null, ?string $appCode = null)
    {
        $this->baseUrl   = rtrim(trim((string) ($baseUrl ?? env('CONSOLE_API_URL', ''))), '/');
        $this->token     = trim((string) ($token ?? env('CONSOLE_API_TOKEN', '')));
        $this->appCode   = strtolower(trim((string) ($appCode ?? env('CONTROLLER_APP_CODE', 'build')))) ?: 'build';
        $this->transport = $transport ?? [self::class, 'curlGet'];
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    /**
     * @return array{status:string, data:array<string,mixed>|null, http_status:int|null}
     */
    public function lookup(?int $consoleUserId, string $email): array
    {
        if (! $this->isConfigured()) {
            return ['status' => self::STATUS_NOT_CONFIGURED, 'data' => null, 'http_status' => null];
        }

        $query = ['app_code' => $this->appCode];
        if ($consoleUserId !== null && $consoleUserId > 0) {
            $query['console_user_id'] = (string) $consoleUserId;
        } else {
            $email = strtolower(trim($email));
            if ($email === '') {
                return ['status' => self::STATUS_UNAVAILABLE, 'data' => null, 'http_status' => null];
            }
            $query['email'] = $email;
        }

        $url = $this->baseUrl . '/portal/controller-apps/user-access?' . http_build_query($query);

        try {
            $res = ($this->transport)($url, [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Console user-access lookup failed: ' . $e->getMessage());

            return ['status' => self::STATUS_UNAVAILABLE, 'data' => null, 'http_status' => 0];
        }

        $status = (int) ($res['status'] ?? 0);
        $body   = $res['body'] ?? false;

        if ($body === false || $status < 200 || $status >= 300) {
            log_message('error', 'Console user-access lookup failed HTTP ' . $status);

            return ['status' => self::STATUS_UNAVAILABLE, 'data' => null, 'http_status' => $status];
        }

        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded) || empty($decoded['success']) || ! is_array($decoded['data'] ?? null)) {
            log_message('error', 'Console user-access lookup returned an unexpected body (HTTP ' . $status . ')');

            return ['status' => self::STATUS_UNAVAILABLE, 'data' => null, 'http_status' => $status];
        }

        return ['status' => self::STATUS_OK, 'data' => $decoded['data'], 'http_status' => $status];
    }

    /**
     * @param list<string> $headers
     * @return array{status:int, body:string|false}
     */
    public static function curlGet(string $url, array $headers): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => false];
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => is_string($raw) ? $raw : false];
    }
}
