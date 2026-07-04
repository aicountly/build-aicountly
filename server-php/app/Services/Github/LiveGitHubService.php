<?php

namespace App\Services\Github;

use App\Models\GithubActivityModel;
use RuntimeException;

/**
 * Real GitHub REST v3 client. All requests carry the token in the
 * Authorization header and use application/vnd.github+json. Every mutating
 * call records a build_github_activity row.
 *
 * Deliberately does not depend on Guzzle so the composer footprint stays
 * to codeigniter4/framework + firebase/php-jwt.
 */
class LiveGitHubService implements GitHubServiceInterface
{
    private const TIMEOUT_SECONDS = 20;

    private string $token;
    private string $baseUrl;
    private string $defaultBranch;
    private string $branchPrefix;
    private GithubActivityModel $activity;

    public function __construct()
    {
        $this->token         = (string) env('BUILD_GITHUB_TOKEN', '');
        $this->baseUrl       = rtrim((string) env('BUILD_GITHUB_API_BASE_URL', 'https://api.github.com'), '/');
        $this->defaultBranch = (string) env('BUILD_GITHUB_DEFAULT_BRANCH', 'main');
        $this->branchPrefix  = (string) env('BUILD_GITHUB_WORKING_BRANCH_PREFIX', 'build-bot/');
        $this->activity      = new GithubActivityModel();
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    public function listRepos(): array
    {
        $org = (string) env('BUILD_GITHUB_ORG', 'AICOUNTLY');
        $res = $this->req('GET', "/orgs/{$org}/repos?per_page=100");
        $out = [];
        foreach (($res ?: []) as $repo) {
            $out[] = [
                'name'            => $repo['name']           ?? '',
                'full_name'       => $repo['full_name']      ?? '',
                'default_branch'  => $repo['default_branch'] ?? 'main',
                'private'         => (bool) ($repo['private'] ?? false),
            ];
        }
        return $out;
    }

    public function getRepo(string $owner, string $repo): array
    {
        return (array) $this->req('GET', "/repos/{$owner}/{$repo}");
    }

    public function readFile(string $owner, string $repo, string $path, ?string $ref = null): string
    {
        $query = $ref ? '?ref=' . rawurlencode($ref) : '';
        $res   = (array) $this->req('GET', "/repos/{$owner}/{$repo}/contents/" . $this->escapePath($path) . $query);
        $content = (string) ($res['content'] ?? '');
        return base64_decode(str_replace(["\n", "\r"], '', $content)) ?: '';
    }

    public function createBranch(string $owner, string $repo, string $newBranch, string $fromBranch): void
    {
        $this->assertNotProtected($newBranch);
        $this->assertPrefix($newBranch);

        // Look up SHA of source branch.
        $refFrom = (array) $this->req('GET', "/repos/{$owner}/{$repo}/git/refs/heads/" . rawurlencode($fromBranch));
        $sha     = (string) ($refFrom['object']['sha'] ?? '');
        if ($sha === '') {
            throw new RuntimeException("Could not resolve source branch '{$fromBranch}' on {$owner}/{$repo}.");
        }

        $this->req('POST', "/repos/{$owner}/{$repo}/git/refs", [
            'ref' => 'refs/heads/' . $newBranch,
            'sha' => $sha,
        ]);

        $this->recordActivity(null, null, 'branch.create', $newBranch, [
            'owner' => $owner, 'repo' => $repo, 'from' => $fromBranch,
        ]);
    }

    public function createOrUpdateFile(string $owner, string $repo, string $branch, string $path, string $content, string $message, ?string $sha = null): array
    {
        $this->assertNotProtected($branch);

        $body = [
            'message' => $message,
            'branch'  => $branch,
            'content' => base64_encode($content),
        ];
        if ($sha !== null && $sha !== '') {
            $body['sha'] = $sha;
        }

        $res = (array) $this->req('PUT', "/repos/{$owner}/{$repo}/contents/" . $this->escapePath($path), $body);

        $this->recordActivity(null, null, 'file.upsert', $branch, [
            'owner' => $owner, 'repo' => $repo, 'path' => $path, 'commit' => $res['commit']['sha'] ?? null,
        ]);

        return $res;
    }

    public function createCommit(string $owner, string $repo, string $branch, string $message, array $filesByPath): array
    {
        $this->assertNotProtected($branch);

        // Fetch tip commit of the branch.
        $ref   = (array) $this->req('GET', "/repos/{$owner}/{$repo}/git/refs/heads/" . rawurlencode($branch));
        $tipSha = (string) ($ref['object']['sha'] ?? '');
        $tip   = (array) $this->req('GET', "/repos/{$owner}/{$repo}/git/commits/{$tipSha}");
        $baseTree = (string) ($tip['tree']['sha'] ?? '');

        // Build new tree with the changed files.
        $treeEntries = [];
        foreach ($filesByPath as $path => $contentOrNull) {
            $treeEntries[] = [
                'path'    => $path,
                'mode'    => '100644',
                'type'    => 'blob',
                'content' => $contentOrNull ?? '',
            ];
        }

        $newTree = (array) $this->req('POST', "/repos/{$owner}/{$repo}/git/trees", [
            'base_tree' => $baseTree,
            'tree'      => $treeEntries,
        ]);

        $commit = (array) $this->req('POST', "/repos/{$owner}/{$repo}/git/commits", [
            'message' => $message,
            'tree'    => (string) ($newTree['sha'] ?? ''),
            'parents' => [$tipSha],
        ]);

        // Fast-forward the branch.
        $this->req('PATCH', "/repos/{$owner}/{$repo}/git/refs/heads/" . rawurlencode($branch), [
            'sha'   => (string) ($commit['sha'] ?? ''),
            'force' => false,
        ]);

        $this->recordActivity(null, null, 'commit.create', $branch, [
            'owner' => $owner, 'repo' => $repo, 'commit' => $commit['sha'] ?? null, 'file_count' => count($filesByPath),
        ]);

        return $commit;
    }

    public function createPullRequest(string $owner, string $repo, string $title, string $head, string $base, string $body): array
    {
        $this->assertNotProtected($head);
        $res = (array) $this->req('POST', "/repos/{$owner}/{$repo}/pulls", [
            'title' => $title,
            'head'  => $head,
            'base'  => $base,
            'body'  => $body,
        ]);
        $this->recordActivity(null, null, 'pr.create', $head, [
            'owner' => $owner, 'repo' => $repo, 'pr_number' => $res['number'] ?? null, 'html_url' => $res['html_url'] ?? null,
        ]);
        return $res;
    }

    public function getPullRequest(string $owner, string $repo, int $prNumber): array
    {
        return (array) $this->req('GET', "/repos/{$owner}/{$repo}/pulls/{$prNumber}");
    }

    public function getWorkflowRuns(string $owner, string $repo, ?string $branch = null): array
    {
        $query = $branch ? '?branch=' . rawurlencode($branch) : '';
        return (array) $this->req('GET', "/repos/{$owner}/{$repo}/actions/runs" . $query);
    }

    public function getCommitDiffSummary(string $owner, string $repo, string $sha): array
    {
        return (array) $this->req('GET', "/repos/{$owner}/{$repo}/commits/{$sha}");
    }

    private function assertNotProtected(string $branch): void
    {
        if ($branch === $this->defaultBranch || $branch === 'main' || $branch === 'master' || $branch === 'production') {
            throw new RuntimeException("Refusing to write to protected branch '{$branch}'.");
        }
    }

    private function assertPrefix(string $branch): void
    {
        if ($this->branchPrefix !== '' && ! str_starts_with($branch, $this->branchPrefix)) {
            throw new RuntimeException("Branch '{$branch}' must start with '{$this->branchPrefix}'.");
        }
    }

    private function escapePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function req(string $method, string $path, array $body = []): mixed
    {
        if (! $this->isConfigured()) {
            throw new NotConfiguredException('GitHub token missing.');
        }

        $url = $this->baseUrl . $path;
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $this->token,
                'User-Agent: aicountly-build (build.aicountly.org)',
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
            ],
        ]);

        if ($body !== [] || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $resBody = curl_exec($ch);
        $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err     = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new RuntimeException("GitHub network error: {$err}");
        }
        if ($status < 200 || $status >= 300) {
            $shortBody = substr((string) $resBody, 0, 400);
            throw new RuntimeException("GitHub API {$method} {$path} failed with HTTP {$status}: {$shortBody}");
        }

        return $resBody === '' ? null : json_decode((string) $resBody, true);
    }

    private function recordActivity(?int $repoId, ?int $devRequestId, string $kind, string $ref, array $payload): void
    {
        try {
            $this->activity->insert([
                'repo_id'        => $repoId,
                'dev_request_id' => $devRequestId,
                'kind'           => $kind,
                'ref'            => $ref,
                'actor'          => 'build.bot',
                'payload'        => json_encode($payload),
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'GitHub activity log failed: ' . $e->getMessage());
        }
    }
}
