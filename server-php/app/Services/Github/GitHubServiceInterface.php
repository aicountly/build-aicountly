<?php

namespace App\Services\Github;

/**
 * Every GitHub-write action from Build passes through this interface.
 * Two implementations:
 *   - LiveGitHubService: talks to api.github.com with BUILD_GITHUB_TOKEN.
 *   - NullGitHubService: throws NotConfiguredException on every write
 *                        (chosen automatically when the token is absent).
 *
 * Rule 8 (never push main / protected branch) is enforced inside
 * SafetyGuardService AND inside LiveGitHubService::createBranch/createCommit
 * so the constraint holds even if callers forget the guard call.
 */
interface GitHubServiceInterface
{
    public function isConfigured(): bool;

    /** @return list<array{name: string, full_name: string, default_branch: string, private: bool}> */
    public function listRepos(): array;

    /** @return array<string, mixed> */
    public function getRepo(string $owner, string $repo): array;

    public function readFile(string $owner, string $repo, string $path, ?string $ref = null): string;

    public function createBranch(string $owner, string $repo, string $newBranch, string $fromBranch): void;

    public function createOrUpdateFile(
        string $owner,
        string $repo,
        string $branch,
        string $path,
        string $content,
        string $message,
        ?string $sha = null,
    ): array;

    public function createCommit(
        string $owner,
        string $repo,
        string $branch,
        string $message,
        array $filesByPath,
    ): array;

    public function createPullRequest(
        string $owner,
        string $repo,
        string $title,
        string $head,
        string $base,
        string $body,
    ): array;

    public function getPullRequest(string $owner, string $repo, int $prNumber): array;

    public function getWorkflowRuns(string $owner, string $repo, ?string $branch = null): array;

    public function getCommitDiffSummary(string $owner, string $repo, string $sha): array;
}
