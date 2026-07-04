<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Real CORS enforcement lives in App\Filters\CorsFilter (env-driven).
 * This CI4 config object is kept minimal so the framework's built-in
 * OPTIONS handler doesn't fight our filter.
 */
class Cors extends BaseConfig
{
    public array $default = [
        'allowedOrigins'         => [],
        'allowedOriginsPatterns' => [],
        'supportsCredentials'    => true,
        'allowedHeaders'         => ['Authorization', 'Content-Type', 'X-Worker-Token', 'X-Source', 'X-Requested-With'],
        'exposedHeaders'         => [],
        'allowedMethods'         => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'maxAge'                 => 7200,
    ];
}
