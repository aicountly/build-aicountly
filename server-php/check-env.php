<?php

/**
 * AICOUNTLY Build — .env sanity checker.
 * Run with:  php check-env.php
 *
 * Prints one line per required env key + integration status without exposing
 * any secret values. Safe to run in production over SSH.
 */

if (! file_exists(__DIR__ . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php missing. Run `composer install` first.\n");
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

$dotEnv = new \CodeIgniter\Config\DotEnv(__DIR__);
$dotEnv->load();

$required = [
    'BUILD_DB_HOST', 'BUILD_DB_PORT', 'BUILD_DB_NAME', 'BUILD_DB_USER', 'BUILD_DB_PASSWORD',
    'BUILD_JWT_SECRET', 'BUILD_VAULT_KEY',
    'BUILD_ALLOWED_ORIGINS',
];

$optional = [
    'BUILD_GITHUB_TOKEN'      => 'GitHub integration',
    'BUILD_WORKER_API_TOKEN'  => 'Playwright worker',
    'BUILD_FLOW_INBOUND_TOKEN'=> 'Flow inbound handoff',
    'BUILD_CONSOLE_API_TOKEN' => 'Console outbound',
    'BUILD_CONSOLE_INBOUND_TOKEN' => 'Console callback',
    'BUILD_AI_PROVIDER'       => 'AI provider selector',
];

$ok = true;

echo "==> Required env keys\n";
foreach ($required as $key) {
    $val = (string) (getenv($key) ?: '');
    $present = $val !== '';
    if (in_array($key, ['BUILD_JWT_SECRET', 'BUILD_VAULT_KEY'], true) && $present) {
        $length = strlen($val);
        $tooShort = $key === 'BUILD_JWT_SECRET' ? $length < 32 : $length !== 64;
        if ($tooShort) {
            $ok = false;
            printf("  [FAIL] %-28s length %d (need %s)\n", $key, $length, $key === 'BUILD_JWT_SECRET' ? '>=32' : '=64');
            continue;
        }
    }
    if (! $present) {
        $ok = false;
        printf("  [FAIL] %-28s missing\n", $key);
    } else {
        printf("  [ ok ] %-28s set\n", $key);
    }
}

echo "\n==> Optional integrations\n";
foreach ($optional as $key => $label) {
    $val = (string) (getenv($key) ?: '');
    printf("  [%s] %-28s %s\n", $val !== '' ? 'set' : ' -- ', $key, $label);
}

echo "\n";
echo $ok ? "All required env keys present.\n" : "One or more required env keys are missing. See above.\n";
exit($ok ? 0 : 1);
