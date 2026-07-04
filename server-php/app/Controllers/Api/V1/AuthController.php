<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\UsersModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class AuthController extends BaseController
{
    public function login(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['email', 'password']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $model = new UsersModel();
        $user = $model->findByEmail((string) $data['email']);
        if (! $user || $user['status'] !== 'active') {
            return build_json_error('Invalid credentials.', [], 401);
        }

        if (! password_verify((string) $data['password'], (string) $user['password_hash'])) {
            $model->update($user['id'], ['failed_attempts' => (int) $user['failed_attempts'] + 1]);
            return build_json_error('Invalid credentials.', [], 401);
        }

        $model->update($user['id'], [
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $this->request->getIPAddress(),
            'failed_attempts' => 0,
        ]);

        $token = Services::jwt()->issue((int) $user['id'], (string) $user['email'], [(string) ($user['role'] ?? 'super_admin')]);

        Services::auditService()->log('auth.login', [
            'actor_id'    => (int) $user['id'],
            'actor_email' => (string) $user['email'],
            'metadata'    => ['ip' => $this->request->getIPAddress()],
        ]);

        return build_json_success([
            'token' => $token,
            'user'  => [
                'id'    => (int) $user['id'],
                'email' => (string) $user['email'],
                'name'  => (string) $user['name'],
                'roles' => [(string) ($user['role'] ?? 'super_admin')],
            ],
        ], 'Signed in.');
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
        return build_json_success([
            'id'    => (int) ($user['id']    ?? $u['id']),
            'email' => (string) ($user['email'] ?? $u['email']),
            'name'  => (string) ($user['name']  ?? ''),
            'roles' => $u['roles'],
        ]);
    }
}
