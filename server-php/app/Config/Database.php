<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * DB credentials MUST come from .env (BUILD_DB_*). No hardcoded values —
 * only sensible non-secret defaults (host/port/database name for local dev).
 */
class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    public string $defaultGroup = 'default';

    public array $default = [
        'DSN'      => '',
        'hostname' => '',
        'username' => '',
        'password' => '',
        'database' => '',
        'DBDriver' => 'Postgre',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => true,
        'charset'  => 'utf8',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port'     => 5432,
        'schema'   => 'public',
    ];

    public array $tests = [
        'DSN'      => '',
        'hostname' => '127.0.0.1',
        'username' => '',
        'password' => '',
        'database' => '',
        'DBDriver' => 'Postgre',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => true,
        'charset'  => 'utf8',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port'     => 5432,
        'schema'   => 'public',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->default['hostname'] = env('BUILD_DB_HOST', '127.0.0.1');
        $this->default['port']     = (int) env('BUILD_DB_PORT', '5432');
        $this->default['database'] = env('BUILD_DB_NAME', 'build_aicountly');
        $this->default['username'] = env('BUILD_DB_USER', '');
        $this->default['password'] = env('BUILD_DB_PASSWORD', '');

        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }
}
