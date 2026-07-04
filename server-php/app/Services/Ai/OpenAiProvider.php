<?php

namespace App\Services\Ai;

/**
 * OpenAI / Codex-style coding model adapter. Configuration:
 *   BUILD_AI_OPENAI_API_KEY, BUILD_AI_OPENAI_MODEL, BUILD_AI_OPENAI_API_BASE_URL
 *
 * Scaffold implementation — see ClaudeProvider for the same rationale.
 */
class OpenAiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private MockProvider $fallback;

    public function __construct()
    {
        $this->apiKey  = (string) env('BUILD_AI_OPENAI_API_KEY', '');
        $this->model   = (string) env('BUILD_AI_OPENAI_MODEL', 'gpt-4.1-mini');
        $this->baseUrl = rtrim((string) env('BUILD_AI_OPENAI_API_BASE_URL', 'https://api.openai.com'), '/');
        $this->fallback = new MockProvider();
    }

    public function name(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function analyzeRequirement(string $requirement, array $context = []): array
    {
        $out = $this->fallback->analyzeRequirement($requirement, $context);
        $out['raw'] = ['provider' => 'openai', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function preparePlan(string $requirement, array $context = []): array
    {
        $out = $this->fallback->preparePlan($requirement, $context);
        $out['raw'] = ['provider' => 'openai', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function generateCodeChanges(array $plan, array $context = []): array
    {
        $out = $this->fallback->generateCodeChanges($plan, $context);
        $out['raw'] = ['provider' => 'openai', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function summarizeDiff(string $diffText, array $context = []): array
    {
        return $this->fallback->summarizeDiff($diffText, $context);
    }
}
