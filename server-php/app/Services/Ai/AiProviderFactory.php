<?php

namespace App\Services\Ai;

/**
 * Chooses an AiProviderInterface implementation based on BUILD_AI_PROVIDER.
 * When the selected provider is not configured, silently falls back to
 * MockProvider so the workflow never breaks — misconfigurations show up
 * in /health/integrations, never as runtime exceptions.
 */
class AiProviderFactory
{
    public static function make(?string $override = null): AiProviderInterface
    {
        $name = strtolower(trim($override ?? (string) env('BUILD_AI_PROVIDER', 'mock')));

        $provider = match ($name) {
            'claude', 'anthropic'  => new ClaudeProvider(),
            'openai', 'codex'      => new OpenAiProvider(),
            'aicountly'            => new AicountlyAiProvider(),
            default                => new MockProvider(),
        };

        if (! $provider->isConfigured()) {
            log_message('info', 'AI provider "' . $name . '" is not configured — using MockProvider fallback.');
            return new MockProvider();
        }

        return $provider;
    }

    /**
     * @return array{provider: string, configured: bool, model?: string|null}
     */
    public static function status(): array
    {
        $name = strtolower(trim((string) env('BUILD_AI_PROVIDER', 'mock')));
        $provider = match ($name) {
            'claude', 'anthropic'  => new ClaudeProvider(),
            'openai', 'codex'      => new OpenAiProvider(),
            'aicountly'            => new AicountlyAiProvider(),
            default                => new MockProvider(),
        };

        return [
            'provider'   => $provider->name(),
            'configured' => $provider->isConfigured(),
        ];
    }
}
