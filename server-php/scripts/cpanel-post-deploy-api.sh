#!/usr/bin/env bash
# Post-deploy hook for Build server-php on cPanel (run via SSH after api deploy).
# Never overwrites api/.env secrets — only quotes unquoted values that contain
# spaces so CodeIgniter 4 DotEnv can parse them (e.g. BUILD_OWNER_NAME).

set -euo pipefail

API_DIR="${1:-.}"
cd "$API_DIR"

if [ ! -f .env ]; then
  echo "ERROR: missing .env in ${API_DIR}"
  echo "Create api/.env manually on the server (copy from .env.example) before deploy."
  exit 1
fi

echo ".env present — normalizing DotEnv format only (no secret value changes)"

fix_dotenv_unquoted_spaces() {
  php <<'PHP'
<?php
$path = '.env';
if (! is_file($path)) {
    exit(0);
}
$lines = file($path, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Could not read .env\n");
    exit(1);
}
$out = [];
$changed = false;
foreach ($lines as $line) {
    $trim = ltrim($line);
    if ($trim === '' || $trim[0] === '#') {
        $out[] = $line;
        continue;
    }
    if (! str_contains($line, '=')) {
        $out[] = $line;
        continue;
    }
    [$key, $val] = explode('=', $line, 2);
    $val = trim($val);
    if ($val !== '' && preg_match('/\s/', $val) && ! preg_match('/^["\']/', $val)) {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $val);
        $line = $key . '="' . $escaped . '"';
        $changed = true;
        fwrite(STDERR, 'Quoted unquoted .env value: ' . trim($key) . "\n");
    }
    $out[] = $line;
}
if ($changed) {
    copy($path, $path . '.bak-' . gmdate('YmdHis'));
    file_put_contents($path, implode("\n", $out) . "\n");
}
PHP
}

fix_dotenv_unquoted_spaces

mkdir -p writable/cache writable/session writable/logs writable/uploads
chmod -R 775 writable/cache writable/session writable/logs writable/uploads 2>/dev/null || \
  chmod -R 777 writable/cache writable/session writable/logs writable/uploads

echo "Post-deploy .env normalization complete."
