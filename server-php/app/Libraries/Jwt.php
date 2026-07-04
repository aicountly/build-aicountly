<?php

namespace App\Libraries;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use RuntimeException;
use UnexpectedValueException;

/**
 * Thin HS256 JWT wrapper for the Build portal's independent superadmin auth.
 * TTL is configured via BUILD_JWT_TTL_MINUTES (default 720 = 12h).
 */
class Jwt
{
    private string $secret;
    private int $ttlSeconds;

    public function __construct(?string $secret = null, ?int $ttlMinutes = null)
    {
        $secret  ??= (string) env('BUILD_JWT_SECRET', '');
        $minutes = $ttlMinutes ?? (int) env('BUILD_JWT_TTL_MINUTES', 720);

        if ($secret === '' || strlen($secret) < 32) {
            throw new RuntimeException('BUILD_JWT_SECRET must be at least 32 characters. Set it in server-php/.env.');
        }

        $this->secret     = $secret;
        $this->ttlSeconds = max(60, $minutes * 60);
    }

    /**
     * @param list<string> $roles
     */
    public function issue(int $userId, string $email, array $roles): string
    {
        $now = time();

        return FirebaseJWT::encode([
            'iss'   => 'build.aicountly.org',
            'aud'   => 'build-portal',
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $now + $this->ttlSeconds,
            'sub'   => (string) $userId,
            'email' => $email,
            'roles' => array_values($roles),
        ], $this->secret, 'HS256');
    }

    /**
     * @return array{sub:string,email:string,roles:array<int,string>,exp:int}|null
     */
    public function decode(string $token): ?array
    {
        try {
            $payload = FirebaseJWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (UnexpectedValueException|\Throwable $e) {
            return null;
        }

        $arr = (array) $payload;
        $arr['roles'] = (array) ($arr['roles'] ?? []);
        return $arr;
    }
}
