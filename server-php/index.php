<?php

/**
 * AICOUNTLY Build Portal — CodeIgniter 4.6 front controller.
 * Deployed to cPanel public_html/api/index.php.
 */

use CodeIgniter\Boot;
use Config\Paths;

$minPhpVersion = '8.1';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo sprintf('PHP %s+ required. Current: %s', $minPhpVersion, PHP_VERSION);
    exit(1);
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);

define('COMPOSER_PATH', FCPATH . 'vendor/autoload.php');

if (! is_file(COMPOSER_PATH)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Composer dependencies missing. Run `composer install` inside server-php/.',
        'data'    => null,
        'errors'  => [],
    ]);
    exit;
}

require FCPATH . 'app/Config/Paths.php';

$paths = new Paths();

require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
