<?php

namespace App\Libraries;

/**
 * Read-only .env loader compatible with CodeIgniter boot.
 *
 * CodeIgniter's strict DotEnv rejects unquoted values containing spaces
 * (e.g. BUILD_OWNER_NAME=Build Superadmin). This loader accepts them so
 * production api/.env is never modified during deploy.
 */
final class LenientDotEnv
{
    public static function load(string $rootPath, string $file = '.env'): void
    {
        $path = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;

        if (! is_file($path) || ! is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name  = self::normalizeName($name);
            $value = self::normalizeValue($value);

            if ($name === '') {
                continue;
            }

            self::setVariable($name, $value);
        }
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/^export[ \t]++(\S+)/', '$1', $name) ?? $name;

        return str_replace(['\'', '"'], '', $name);
    }

    private static function normalizeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        if (strpbrk($value[0], '"\'') !== false) {
            $quote = $value[0];
            $regex = sprintf(
                '/^%1$s((?:[^%1$s\\\\]|\\\\\\\\|\\\\%1$s)*)%1$s.*$/s',
                preg_quote($quote, '/'),
            );
            if (preg_match($regex, $value, $matches) === 1) {
                $value = str_replace(['\\' . $quote, '\\\\'], [$quote, '\\'], $matches[1]);
            }

            return $value;
        }

        $parts = explode(' #', $value, 2);

        return trim($parts[0]);
    }

    private static function setVariable(string $name, string $value): void
    {
        if (getenv($name, true) === false) {
            putenv("{$name}={$value}");
        }

        if (! array_key_exists($name, $_ENV) || $_ENV[$name] === '') {
            $_ENV[$name] = $value;
        }

        if (! array_key_exists($name, $_SERVER) || $_SERVER[$name] === '') {
            $_SERVER[$name] = $value;
        }
    }
}
