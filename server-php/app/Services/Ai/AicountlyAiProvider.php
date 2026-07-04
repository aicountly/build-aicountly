<?php

namespace App\Services\Ai;

/**
 * AICOUNTLY-hosted AI adapter (proxied through internal endpoint).
 * Reads BUILD_AI_AICOUNTLY_API_URL / BUILD_AI_AICOUNTLY_API_KEY / BUILD_AI_AICOUNTLY_MODEL.
 *
 * Scaffold — real request wiring to be filled in once the AICOUNTLY AI
 * wrapper API surface is finalised.
 */
class AicountlyAiProvider implements AiProviderInterface
{
    private string $apiUrl;
    private string $apiKey;
    private string $model;
    private MockProvider $fallback;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) env('BUILD_AI_AICOUNTLY_API_URL', ''), '/');
        $this->apiKey = (string) env('BUILD_AI_AICOUNTLY_API_KEY', '');
        $this->model  = (string) env('BUILD_AI_AICOUNTLY_MODEL', 'aicountly-code-01');
        $this->fallback = new MockProvider();
    }

    public function name(): string
    {
        return 'aicountly';
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && $this->apiKey !== '';
    }

    public function analyzeRequirement(string $requirement, array $context = []): array
    {
        $out = $this->fallback->analyzeRequirement($requirement, $context);
        $out['raw'] = ['provider' => 'aicountly', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function preparePlan(string $requirement, array $context = []): array
    {
        $out = $this->fallback->preparePlan($requirement, $context);
        $out['raw'] = ['provider' => 'aicountly', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function generateCodeChanges(array $plan, array $context = []): array
    {
        $out = $this->fallback->generateCodeChanges($plan, $context);
        $out['raw'] = ['provider' => 'aicountly', 'model' => $this->model, 'configured' => $this->isConfigured()];
        return $out;
    }

    public function summarizeDiff(string $diffText, array $context = []): array
    {
        return $this->fallback->summarizeDiff($diffText, $context);
    }
}
