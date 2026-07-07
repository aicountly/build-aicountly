<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\UsersModel;
use App\Services\ConsoleIdentityService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use RuntimeException;
use Throwable;

class AuthController extends BaseController
{
    private const BUILD_TOKEN_STORAGE_KEY = 'build_token';

    public function login(): ResponseInterface
    {
        return build_json_error(
            'Local login is disabled. Sign in at console.aicountly.org and open Build from Top Controller Apps.',
            [],
            403,
        );
    }

    public function refresh(): ResponseInterface
    {
        $data = $this->jsonInput();
        $token = (string) ($data['token'] ?? '');
        if ($token === '') {
            return build_json_error('Token is required.', ['token' => 'required'], 422);
        }

        $payload = Services::jwt()->decode($token);
        if (! $payload) {
            return build_json_error('Invalid or expired token.', [], 401);
        }

        $email = (string) ($payload['email'] ?? '');
        $roles = (array) ($payload['roles'] ?? ['super_admin']);
        $userId = (int) ($payload['sub'] ?? 0);

        $model = new UsersModel();
        $user  = $model->find($userId);
        if (! $user || $user['status'] !== 'active') {
            return build_json_error('User not active.', [], 401);
        }

        $fresh = Services::jwt()->issue($userId, $email, $roles);

        return build_json_success(['token' => $fresh, 'user' => [
            'id'    => $userId,
            'email' => $email,
            'name'  => (string) $user['name'],
            'roles' => $roles,
        ]], 'Token refreshed.');
    }

    public function logout(): ResponseInterface
    {
        Services::auditService()->log('auth.logout');
        return build_json_success(null, 'Signed out.');
    }

    public function me(): ResponseInterface
    {
        $u = $this->currentUser();
        if (! $u) {
            return build_json_unauthorized();
        }
        $model = new UsersModel();
        $user  = $model->find($u['id']) ?? [];
        $role  = (string) ($user['role'] ?? 'super_admin');

        return build_json_success([
            'id'              => (int) ($user['id']    ?? $u['id']),
            'email'           => (string) ($user['email'] ?? $u['email']),
            'name'            => (string) ($user['name']  ?? ''),
            'roles'           => [$role],
            'controller_apps' => $this->controllerAppsForRequest(),
        ]);
    }

    public function controllerAppsLauncher(): ResponseInterface
    {
        $u = $this->currentUser();
        if (! $u) {
            return build_json_unauthorized();
        }

        $apps = $this->controllerAppsForRequest();
        if ($apps === []) {
            return build_json_error(
                'Console session required for Top Controller Apps. Sign in at console.aicountly.org first.',
                [],
                401,
            );
        }

        return build_json_success([
            'apps' => $apps,
        ]);
    }

    public function ssoLaunchUrl(): ResponseInterface
    {
        $u = $this->currentUser();
        if (! $u) {
            return build_json_unauthorized();
        }

        $appCode = strtolower(trim((string) ($this->request->getGet('app_code') ?? '')));
        if ($appCode === '') {
            return build_json_error('app_code query parameter is required.', ['app_code' => 'required'], 422);
        }

        $consoleToken = $this->consoleTokenFromRequest();
        if ($consoleToken === '') {
            return build_json_error('Console session required to launch controller apps.', [], 401);
        }

        $data = Services::consoleIdentity()->getSsoLaunchUrl($consoleToken, $appCode);
        $redirectUrl = trim((string) ($data['redirect_url'] ?? ''));
        if ($redirectUrl === '') {
            return build_json_error('Console did not return a launch URL for this app.', [], 502);
        }

        return build_json_success(['redirect_url' => $redirectUrl]);
    }

    /**
     * GET /v1/auth/sso-callback?token= — browser redirect from Console (no SPA JS required).
     */
    public function ssoCallback(): ResponseInterface
    {
        try {
            if ($fail = $this->ensureJwtConfigured()) {
                return $this->ssoCallbackHtml('Build Portal is not configured for Console SSO yet.', 503);
            }

            $token = trim((string) ($this->request->getGet('token') ?? ''));
            if ($token === '') {
                return $this->ssoCallbackHtml('Missing SSO token. Open Build again from Console Top Controller Apps.', 400);
            }

            $identity = Services::consoleIdentity()->exchangeLaunchToken($token);
            if ($identity === null) {
                return $this->ssoCallbackHtml(
                    'This sign-in link expired. Go back to Console and click Build again.',
                    401,
                );
            }

            $session = $this->buildSessionFromConsoleIdentity($identity, 'auth.controller_sso_callback');
            if ($session instanceof ResponseInterface) {
                $message = 'You do not have access to the Build controller app.';
                $json = json_decode($session->getBody(), true);
                if (is_array($json) && ! empty($json['message'])) {
                    $message = (string) $json['message'];
                }

                return $this->ssoCallbackHtml($message, 403);
            }

            return $this->completeSsoInBrowser((string) $session['token']);
        } catch (Throwable $e) {
            log_message('error', 'SSO callback failed: ' . $e->getMessage());

            return $this->ssoCallbackHtml('Console SSO sign-in failed. Try again from Console.', 500);
        }
    }

    /**
     * Exchange a Console controller SSO launch token for a Build session.
     */
    public function controllerSso(): ResponseInterface
    {
        try {
            if ($fail = $this->ensureJwtConfigured()) {
                return $fail;
            }

            $data  = $this->jsonInput();
            $token = trim((string) ($data['token'] ?? ''));
            if ($token === '') {
                return build_json_error('token required.', ['token' => 'required'], 400);
            }

            $identity = Services::consoleIdentity()->exchangeLaunchToken($token);
            if ($identity === null) {
                return build_json_error('Invalid or expired Console SSO token.', [], 401);
            }

            $session = $this->buildSessionFromConsoleIdentity($identity, 'auth.controller_sso_login');
            if ($session instanceof ResponseInterface) {
                return $session;
            }

            return build_json_success($session, 'Signed in via Console SSO.');
        } catch (Throwable $e) {
            log_message('error', 'Controller SSO failed: ' . $e->getMessage());

            return build_json_error('Controller SSO login failed.', [], 500);
        }
    }

    /**
     * Sign in using the shared Console cookie (direct visit to build.aicountly.org).
     */
    public function consoleSession(): ResponseInterface
    {
        try {
            if ($fail = $this->ensureJwtConfigured()) {
                return $fail;
            }

            $consoleToken = trim((string) ($this->request->getCookie(ConsoleIdentityService::cookieName()) ?? ''));
            if ($consoleToken === '') {
                return build_json_error('Sign in to Console first.', [], 401);
            }

            $identity = Services::consoleIdentity()->introspectSession($consoleToken);
            if ($identity === null) {
                return build_json_error('Console session is invalid or expired. Sign in again at Console.', [], 401);
            }

            $session = $this->buildSessionFromConsoleIdentity($identity, 'auth.console_session_login');
            if ($session instanceof ResponseInterface) {
                return $session;
            }

            return build_json_success($session, 'Signed in via Console session.');
        } catch (Throwable $e) {
            log_message('error', 'Console session login failed: ' . $e->getMessage());

            return build_json_error('Console session login failed.', [], 500);
        }
    }

    /**
     * @param array<string,mixed> $identity
     * @return array<string,mixed>|ResponseInterface
     */
    private function buildSessionFromConsoleIdentity(array $identity, string $auditEvent): array|ResponseInterface
    {
        $active = (bool) ($identity['active'] ?? false);
        $global = (bool) ($identity['global_superadmin'] ?? false);
        if (! $active && ! $global) {
            return build_json_error('You do not have access to the Build controller app.', [], 403);
        }

        $consoleUser = is_array($identity['user'] ?? null) ? $identity['user'] : [];
        $email = strtolower(trim((string) ($consoleUser['email'] ?? '')));
        $name  = trim((string) ($consoleUser['name'] ?? ''));
        if ($email === '') {
            return build_json_error('Console identity did not return a user email.', [], 502);
        }

        $users = new UsersModel();
        $user  = $users->findByEmail($email);
        $role  = 'super_admin';

        if (! $user) {
            $userId = $users->insert([
                'email'         => $email,
                'name'          => $name !== '' ? $name : $email,
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'status'        => 'active',
                'role'          => $role,
            ]);

            if (! $userId) {
                return build_json_error('Could not provision Build user from Console identity.', [], 500);
            }

            $user = $users->find((int) $userId);
        } elseif (($user['status'] ?? 'active') !== 'active') {
            return build_json_error('Build user account is inactive.', [], 403);
        }

        $role = (string) ($user['role'] ?? $role);
        $roles = [$role];

        try {
            $buildToken = Services::jwt()->issue((int) $user['id'], $user['email'], $roles);
        } catch (RuntimeException $e) {
            return build_json_error($e->getMessage(), [], 503);
        }

        $users->update($user['id'], [
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $this->request->getIPAddress(),
            'failed_attempts' => 0,
        ]);

        Services::auditService()->log($auditEvent, [
            'actor_id'    => (int) $user['id'],
            'actor_email' => $user['email'],
            'actor_role'  => $role,
            'metadata'    => [
                'console_user_id'   => (int) ($consoleUser['id'] ?? 0),
                'global_superadmin' => $global,
            ],
        ]);

        return [
            'token'   => $buildToken,
            'expires' => (int) env('BUILD_JWT_TTL_MINUTES', 720) * 60,
            'user'    => [
                'id'              => (int) $user['id'],
                'email'           => $user['email'],
                'name'            => $user['name'],
                'roles'           => $roles,
                'controller_apps' => $this->normalizeLauncherApps(
                    is_array($identity['controller_apps'] ?? null) ? $identity['controller_apps'] : [],
                ),
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function controllerAppsForRequest(): array
    {
        $consoleToken = $this->consoleTokenFromRequest();
        if ($consoleToken === '') {
            return [];
        }

        $data = Services::consoleIdentity()->getLauncherApps($consoleToken);
        if (! is_array($data)) {
            return [];
        }

        return $this->normalizeLauncherApps(
            is_array($data['apps'] ?? null) ? $data['apps'] : [],
        );
    }

    private function consoleTokenFromRequest(): string
    {
        return trim((string) ($this->request->getCookie(ConsoleIdentityService::cookieName()) ?? ''));
    }

    /**
     * @param list<array<string,mixed>> $apps
     * @return list<array<string,mixed>>
     */
    private function normalizeLauncherApps(array $apps): array
    {
        $current = strtolower(trim((string) env('CONTROLLER_APP_CODE', 'build')));

        return array_values(array_map(static function (array $app) use ($current): array {
            $code = strtolower(trim((string) ($app['code'] ?? '')));
            $app['is_current'] = $code === $current;

            return $app;
        }, $apps));
    }

    private function completeSsoInBrowser(string $buildToken): ResponseInterface
    {
        $tokenJson = json_encode($buildToken, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $storageKey = json_encode(self::BUILD_TOKEN_STORAGE_KEY, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Signing in to Build Portal…</title>
  <style>
    body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; color: #334155; }
  </style>
</head>
<body>
  <p>Signing you in to Build Portal…</p>
  <script>
    try {
      localStorage.setItem({$storageKey}, {$tokenJson});
    } catch (e) {}
    location.replace('/');
  </script>
</body>
</html>
HTML;

        return $this->response
            ->setStatusCode(200)
            ->setContentType('text/html')
            ->setBody($html);
    }

    private function ssoCallbackHtml(string $message, int $status = 400): ResponseInterface
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $consoleUrl  = 'https://console.aicountly.org';
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Build sign-in failed</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 420px; margin: 48px auto; padding: 0 16px; color: #334155; }
    .box { border: 1px solid #fecaca; background: #fef2f2; border-radius: 12px; padding: 16px; }
    a { color: #047857; }
  </style>
</head>
<body>
  <div class="box">
    <h1 style="font-size:18px;margin:0 0 8px;">Build sign-in failed</h1>
    <p style="margin:0 0 12px;">{$safeMessage}</p>
    <p style="margin:0;"><a href="{$consoleUrl}">Return to Console</a></p>
  </div>
</body>
</html>
HTML;

        return $this->response
            ->setStatusCode($status)
            ->setContentType('text/html')
            ->setBody($html);
    }

    private function ensureJwtConfigured(): ?ResponseInterface
    {
        $jwtSecret = (string) env('BUILD_JWT_SECRET', '');
        if ($jwtSecret === '') {
            $jwtSecret = (string) env('JWT_SECRET', '');
        }
        if ($jwtSecret === '' || strlen($jwtSecret) < 32) {
            return build_json_error(
                'Server misconfigured: set BUILD_JWT_SECRET (32+ chars) in server-php/.env',
                [],
                503,
            );
        }

        return null;
    }
}
