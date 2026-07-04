<?php

/**
 * AICOUNTLY Build — PostgreSQL connection probe.
 * Reads BUILD_DB_* from .env and issues `SELECT 1`.
 */

if (! file_exists(__DIR__ . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php missing. Run `composer install` first.\n");
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

(new \CodeIgniter\Config\DotEnv(__DIR__))->load();

$host = getenv('BUILD_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('BUILD_DB_PORT') ?: 5432);
$db   = getenv('BUILD_DB_NAME') ?: 'build_aicountly';
$user = getenv('BUILD_DB_USER') ?: '';
$pass = getenv('BUILD_DB_PASSWORD') ?: '';

$dsn = "pgsql:host={$host};port={$port};dbname={$db}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $one = $pdo->query('SELECT 1 AS one')->fetch();
    printf("OK — %s:%d/%s reachable. SELECT 1 => %s\n", $host, $port, $db, (string) $one['one']);
    exit(0);
} catch (\Throwable $e) {
    fprintf(STDERR, "FAIL — %s:%d/%s: %s\n", $host, $port, $db, $e->getMessage());
    exit(1);
}
