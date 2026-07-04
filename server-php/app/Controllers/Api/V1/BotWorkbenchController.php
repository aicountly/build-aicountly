<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\AiProviderCallsModel;
use App\Models\BotPlansModel;
use App\Models\DevRequestsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Bot Workbench — analyze / plan / dry-run only. These endpoints never
 * write code or touch GitHub. Actual code generation still requires an
 * approved `code` approval and goes through Commit Queue.
 */
class BotWorkbenchController extends BaseController
{
    public function analyze(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['dev_request_id']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $devRequestId = (int) $data['dev_request_id'];
        $dev = (new DevRequestsModel())->find($devRequestId);
        if (! $dev) {
            return build_json_not_found('Development request not found.');
        }

        Services::safetyGuard()->assertReadAllowed($devRequestId, 'bot.analyze');

        $started  = microtime(true);
        $provider = Services::aiProvider();
        $result   = $provider->analyzeRequirement((string) $dev['requirement_text'], ['dev_request' => $dev]);
        $latency  = (int) ((microtime(true) - $started) * 1000);

        (new AiProviderCallsModel())->insert([
            'dev_request_id' => $devRequestId,
            'provider'       => $provider->name(),
            'model'          => (string) ($result['raw']['model'] ?? ''),
            'endpoint'       => 'analyze',
            'tokens_in'      => 0,
            'tokens_out'     => 0,
            'latency_ms'     => $latency,
            'status'         => 'ok',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        Services::devRequestWorkflow()->recordEvent($devRequestId, 'bot.analyzed', 'Bot completed analysis.', $result, 'bot');
        Services::devRequestWorkflow()->transition($devRequestId, 'analyzing', ['actor_kind' => 'bot', 'note' => 'Bot analysis started.']);

        return build_json_success($result);
    }

    public function plan(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['dev_request_id']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $devRequestId = (int) $data['dev_request_id'];
        $dev = (new DevRequestsModel())->find($devRequestId);
        if (! $dev) {
            return build_json_not_found('Development request not found.');
        }

        Services::safetyGuard()->assertReadAllowed($devRequestId, 'bot.plan');

        $started  = microtime(true);
        $provider = Services::aiProvider();
        $plan     = $provider->preparePlan((string) $dev['requirement_text'], ['dev_request' => $dev]);
        $latency  = (int) ((microtime(true) - $started) * 1000);

        $planId = (int) (new BotPlansModel())->insert([
            'dev_request_id'  => $devRequestId,
            'ai_provider'     => $provider->name(),
            'ai_model'        => (string) ($plan['raw']['model'] ?? ''),
            'summary'         => (string) ($plan['summary'] ?? ''),
            'plan'            => json_encode($plan['steps']            ?? []),
            'test_plan'       => json_encode($plan['test_plan']        ?? []),
            'risk_level'      => (string) ($plan['risk_level']         ?? 'medium'),
            'files_estimated' => json_encode($plan['files_estimated']  ?? []),
            'tokens_used'     => (int) ($plan['tokens_used'] ?? 0),
            'created_by_bot'  => true,
        ], true);

        (new AiProviderCallsModel())->insert([
            'dev_request_id' => $devRequestId,
            'provider'       => $provider->name(),
            'model'          => (string) ($plan['raw']['model'] ?? ''),
            'endpoint'       => 'plan',
            'tokens_in'      => 0,
            'tokens_out'     => (int) ($plan['tokens_used'] ?? 0),
            'latency_ms'     => $latency,
            'status'         => 'ok',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        Services::devRequestWorkflow()->transition($devRequestId, 'plan_prepared', [
            'actor_kind' => 'bot',
            'note'       => 'Bot prepared plan.',
            'payload'    => ['plan_id' => $planId],
        ]);

        Services::botReportService()->record($devRequestId, [
            'ai_provider' => $provider->name(),
            'plan'        => $plan['steps']           ?? [],
            'tests_run'   => $plan['test_plan']       ?? [],
            'raw_metadata' => ['stage' => 'plan', 'plan_id' => $planId],
            'next_recommended_action' => 'Request superadmin approval to write code.',
        ]);

        return build_json_success(array_merge($plan, ['plan_id' => $planId]));
    }

    public function generateCode(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['dev_request_id']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $devRequestId = (int) $data['dev_request_id'];

        // Safety Guard — code writes require an approved `code` approval.
        try {
            Services::safetyGuard()->assertAllowed('code', $devRequestId);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 403);
        }

        $dev = (new DevRequestsModel())->find($devRequestId);
        if (! $dev) {
            return build_json_not_found('Development request not found.');
        }

        $plan = (new BotPlansModel())->latestForRequest($devRequestId);
        if (! $plan) {
            return build_json_error('No plan exists for this request. Generate one first.', [], 400);
        }

        $started  = microtime(true);
        $provider = Services::aiProvider();
        $result   = $provider->generateCodeChanges((array) $plan, ['dev_request' => $dev]);
        $latency  = (int) ((microtime(true) - $started) * 1000);

        (new AiProviderCallsModel())->insert([
            'dev_request_id' => $devRequestId,
            'provider'       => $provider->name(),
            'model'          => (string) ($result['raw']['model'] ?? ''),
            'endpoint'       => 'generate_code',
            'tokens_out'     => (int) ($result['tokens_used'] ?? 0),
            'latency_ms'     => $latency,
            'status'         => 'ok',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        Services::devRequestWorkflow()->transition($devRequestId, 'coding', [
            'actor_kind' => 'bot',
            'note'       => 'Bot generated code proposal.',
            'payload'    => ['file_count' => count($result['files'] ?? [])],
        ]);

        return build_json_success($result);
    }
}
