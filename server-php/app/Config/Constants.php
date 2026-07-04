<?php

/*
 |--------------------------------------------------------------------------
 | AICOUNTLY Build — CodeIgniter constants
 |--------------------------------------------------------------------------
 */

defined('DEFAULT_HTTP_METHOD') || define('DEFAULT_HTTP_METHOD', 'get');
defined('DEFAULT_LOCALE')      || define('DEFAULT_LOCALE', 'en');
defined('APP_NAMESPACE')       || define('APP_NAMESPACE', 'App');
defined('CONFIG_PATH')         || define('CONFIG_PATH', __DIR__ . DIRECTORY_SEPARATOR);
defined('WRITEPATH')           || define('WRITEPATH', realpath(__DIR__ . '/../../writable') . DIRECTORY_SEPARATOR);

defined('EXIT_SUCCESS')        || define('EXIT_SUCCESS', 0);
defined('EXIT_ERROR')          || define('EXIT_ERROR', 1);
defined('EXIT_CONFIG')         || define('EXIT_CONFIG', 3);
defined('EXIT_UNKNOWN_FILE')   || define('EXIT_UNKNOWN_FILE', 4);
defined('EXIT_UNKNOWN_CLASS')  || define('EXIT_UNKNOWN_CLASS', 5);
defined('EXIT_UNKNOWN_METHOD') || define('EXIT_UNKNOWN_METHOD', 6);
defined('EXIT_USER_INPUT')     || define('EXIT_USER_INPUT', 7);
defined('EXIT_DATABASE')       || define('EXIT_DATABASE', 8);
defined('EXIT__AUTO_MIN')      || define('EXIT__AUTO_MIN', 9);
defined('EXIT__AUTO_MAX')      || define('EXIT__AUTO_MAX', 125);

/*
 |--------------------------------------------------------------------------
 | Build-portal domain constants
 |--------------------------------------------------------------------------
 */

/** Single superadmin role used by the whole Build portal. */
defined('BUILD_ROLE_SUPERADMIN') || define('BUILD_ROLE_SUPERADMIN', 'super_admin');

/** Cross-portal identity sent on outbound HTTP calls. */
defined('BUILD_PORTAL_SOURCE') || define('BUILD_PORTAL_SOURCE', 'build.aicountly.org');
