<?php

namespace App\Services\Ai;

/**
 * Anthropic Claude adapter (Sonnet / Opus). Reads BUILD_AI_CLAUDE_API_KEY /
 * BUILD_AI_CLAUDE_MODEL / BUILD_AI_CLAUDE_API_BASE_URL from env.
 *
 * The current implementation is a scaffold: it validates configuration and
 * falls back to MockProvider outputs annotated with `provider: claude` so
 * that upstream code (workflow, audit, cost accounting) is exercised while
 * real API wiring is finalised. Swap the fallback for a curl call to
 * /v1/messages when Anthropic access is enabled for the org.
 */
class ClaudeProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private MockProvider $fallback;

    public function __construct()
    {
        $this->apiKey  = (string) env('BUILD_AI_CLAUDE_API_KEY', '');
        $this->model   = (string) env('BUILD_AI_CLAUDE_MODEL', 'claude-sonnet-4');
        $this->baseUrl = rtrim((string) env('BUILD_AI_CLAUDE_API_BASE_URL', 'https://api.anthropic.com'), '/');
        $this->fallback = new MockProvider();
    }

    public function name(): string
    {
        return 'claude';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function analyzeRequirement(string $requirement, array $context = []): array
    {
        $out = $this->fallback->analyzeRequirement($requirement, $context);
        $out['raw'] = ['provider' => 'claude', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function preparePlan(string $requirement, array $context = []): array
    {
        $out = $this->fallback->preparePlan($requirement, $context);
        $out['raw'] = ['provider' => 'claude', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function generateCodeChanges(array $plan, array $context = []): array
    {
        $out = $this->fallback->generateCodeChanges($plan, $context);
        $out['raw'] = ['provider' => 'claude', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function summarizeDiff(string $diffText, array $context = []): array
    {
        return $this->fallback->summarizeDiff($diffText, $context);
    }
}
