<?php

namespace App\Services\Github;

class NullGitHubService implements GitHubServiceInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function listRepos(): array
    {
        throw $this->not();
    }

    public function getRepo(string $owner, string $repo): array
    {
        throw $this->not();
    }

    public function readFile(string $owner, string $repo, string $path, ?string $ref = null): string
    {
        throw $this->not();
    }

    public function createBranch(string $owner, string $repo, string $newBranch, string $fromBranch): void
    {
        throw $this->not();
    }

    public function createOrUpdateFile(string $owner, string $repo, string $branch, string $path, string $content, string $message, ?string $sha = null): array
    {
        throw $this->not();
    }

    public function createCommit(string $owner, string $repo, string $branch, string $message, array $filesByPath): array
    {
        throw $this->not();
    }

    public function createPullRequest(string $owner, string $repo, string $title, string $head, string $base, string $body): array
    {
        throw $this->not();
    }

    public function getPullRequest(string $owner, string $repo, int $prNumber): array
    {
        throw $this->not();
    }

    public function getWorkflowRuns(string $owner, string $repo, ?string $branch = null): array
    {
        throw $this->not();
    }

    public function getCommitDiffSummary(string $owner, string $repo, string $sha): array
    {
        throw $this->not();
    }

    private function not(): NotConfiguredException
    {
        return new NotConfiguredException('GitHub integration is not configured. Set BUILD_GITHUB_TOKEN in server-php/.env.');
    }
}
