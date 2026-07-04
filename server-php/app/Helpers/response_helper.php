<?php

/**
 * AICOUNTLY Build — canonical JSON response helper.
 *
 * All API endpoints return { success, message, data, errors } to match the
 * Flow / Console cross-portal envelope byte-for-byte. Never return the CI4
 * ResponseTrait `respond*` methods directly — they emit a different shape.
 */

use CodeIgniter\HTTP\ResponseInterface;

if (! function_exists('build_json_success')) {
    function build_json_success(mixed $data = null, string $message = 'OK', int $status = 200): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setJSON([
                'success' => true,
                'message' => $message,
                'data'    => $data,
                'errors'  => [],
            ], false, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (! function_exists('build_json_error')) {
    function build_json_error(string $message, array $errors = [], int $status = 400): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setJSON([
                'success' => false,
                'message' => $message,
                'data'    => null,
                'errors'  => $errors,
            ], false, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (! function_exists('build_json_unauthorized')) {
    function build_json_unauthorized(string $message = 'Unauthorized.'): ResponseInterface
    {
        return build_json_error($message, [], 401);
    }
}

if (! function_exists('build_json_forbidden')) {
    function build_json_forbidden(string $message = 'Forbidden.'): ResponseInterface
    {
        return build_json_error($message, [], 403);
    }
}

if (! function_exists('build_json_not_found')) {
    function build_json_not_found(string $message = 'Not found.'): ResponseInterface
    {
        return build_json_error($message, [], 404);
    }
}

if (! function_exists('build_json_conflict')) {
    function build_json_conflict(string $message = 'Conflict.', array $errors = []): ResponseInterface
    {
        return build_json_error($message, $errors, 409);
    }
}
