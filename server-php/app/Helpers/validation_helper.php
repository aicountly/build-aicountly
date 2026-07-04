<?php

/**
 * AICOUNTLY Build — lightweight validation helpers.
 * Complements CI4's Validation library for one-off checks in controllers.
 */

if (! function_exists('build_require_fields')) {
    /**
     * Ensures every required key is present and non-empty on the payload.
     * Returns an associative array of {field => "Field is required."} on failure,
     * or an empty array on success.
     *
     * @param array<string, mixed> $payload
     * @param list<string>         $fields
     * @return array<string, string>
     */
    function build_require_fields(array $payload, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $val = $payload[$field] ?? null;
            if ($val === null || $val === '' || (is_array($val) && $val === [])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        return $errors;
    }
}

if (! function_exists('build_valid_email')) {
    function build_valid_email(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (! function_exists('build_slugify')) {
    function build_slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }
}
