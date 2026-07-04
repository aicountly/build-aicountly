<?php

namespace App\Services\Github;

/**
 * Chooses between LiveGitHubService and NullGitHubService based on whether
 * BUILD_GITHUB_TOKEN is set. Never throws — the null implementation cleanly
 * rejects writes so `/health/integrations` can render "not configured".
 */
class GitHubServiceFactory
{
    public static function make(): GitHubServiceInterface
    {
        $token = (string) env('BUILD_GITHUB_TOKEN', '');
        return $token === '' ? new NullGitHubService() : new LiveGitHubService();
    }

    /**
     * @return array{configured: bool, org: string, default_branch: string, working_branch_prefix: string}
     */
    public static function status(): array
    {
        return [
            'configured'            => (string) env('BUILD_GITHUB_TOKEN', '') !== '',
            'org'                   => (string) env('BUILD_GITHUB_ORG', 'AICOUNTLY'),
            'default_branch'        => (string) env('BUILD_GITHUB_DEFAULT_BRANCH', 'main'),
            'working_branch_prefix' => (string) env('BUILD_GITHUB_WORKING_BRANCH_PREFIX', 'build-bot/'),
        ];
    }
}
