<?php

namespace App\Services\Ai;

/**
 * Uniform AI provider surface used by BotWorkbench and DevRequestWorkflow.
 *
 * Never receives GitHub write access — providers only *plan* and *summarise*.
 * Actual code writes still go through GitHubService, guarded by SafetyGuardService.
 */
interface AiProviderInterface
{
    /** Human-friendly provider id (matches BUILD_AI_PROVIDER). */
    public function name(): string;

    /** True when the provider has all env keys it needs. */
    public function isConfigured(): bool;

    /**
     * Break a raw requirement text into structured signals.
     *
     * @return array{
     *   summary: string,
     *   likely_files: list<string>,
     *   suggested_request_type: string,
     *   suggested_priority: string,
     *   suggested_risk_level: string,
     *   notes: string,
     *   raw?: array<string, mixed>
     * }
     */
    public function analyzeRequirement(string $requirement, array $context = []): array;

    /**
     * Produce an executable plan and a test plan for the request. The plan
     * is *never* executed automatically; superadmin approval is required
     * before any code_task rows are created from it.
     *
     * @return array{
     *   summary: string,
     *   steps: list<array<string, mixed>>,
     *   test_plan: list<array<string, mixed>>,
     *   files_estimated: list<string>,
     *   risk_level: string,
     *   tokens_used: int,
     *   raw?: array<string, mixed>
     * }
     */
    public function preparePlan(string $requirement, array $context = []): array;

    /**
     * Generate a code-change proposal. Returns a diff-like structure that the
     * Commit Queue turns into GitHub API calls only after approval.
     *
     * @return array{
     *   summary: string,
     *   files: list<array{path: string, action: string, before?: string, after: string}>,
     *   tokens_used: int,
     *   raw?: array<string, mixed>
     * }
     */
    public function generateCodeChanges(array $plan, array $context = []): array;

    /**
     * Summarise a diff (from GitHub) into a human-readable paragraph.
     *
     * @return array{summary: string, tokens_used: int}
     */
    public function summarizeDiff(string $diffText, array $context = []): array;
}
