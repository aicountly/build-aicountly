<?php

namespace Config;

use App\Libraries\LenientDotEnv;
use CodeIgniter\Boot;
use ReflectionClass;

/**
 * Boots CodeIgniter without running the strict system DotEnv parser on api/.env.
 */
final class ApplicationBoot
{
    public static function bootWeb(Paths $paths): int
    {
        $invoke = self::invoker();

        $invoke('definePathConstants', $paths);
        if (! defined('APP_NAMESPACE')) {
            $invoke('loadConstants');
        }
        $invoke('checkMissingExtensions');
        self::loadEnvironment($paths);
        $invoke('defineEnvironment');
        $invoke('loadEnvironmentBootstrap', $paths);
        $invoke('loadCommonFunctions');
        $invoke('loadAutoloader');
        $invoke('setExceptionHandler');
        $invoke('initializeKint');
        $invoke('autoloadHelpers');

        $configCacheEnabled = class_exists(Optimize::class)
            && (new Optimize())->configCacheEnabled;
        $factoriesCache     = null;
        if ($configCacheEnabled) {
            $factoriesCache = $invoke('loadConfigCache');
        }

        $app = $invoke('initializeCodeIgniter');
        $invoke('runCodeIgniter', $app);

        if ($configCacheEnabled && $factoriesCache !== null) {
            $invoke('saveConfigCache', $factoriesCache);
        }

        return EXIT_SUCCESS;
    }

    public static function bootSpark(Paths $paths): int
    {
        $invoke = self::invoker();

        $invoke('definePathConstants', $paths);
        if (! defined('APP_NAMESPACE')) {
            $invoke('loadConstants');
        }
        $invoke('checkMissingExtensions');
        self::loadEnvironment($paths);
        $invoke('defineEnvironment');
        $invoke('loadEnvironmentBootstrap', $paths);
        $invoke('loadCommonFunctions');
        $invoke('loadAutoloader');
        $invoke('setExceptionHandler');
        $invoke('initializeKint');
        $invoke('autoloadHelpers');
        $invoke('initializeCodeIgniter');

        $console = $invoke('initializeConsole');
        $exit    = $invoke('runCommand', $console);

        return is_int($exit) ? $exit : EXIT_SUCCESS;
    }

    private static function loadEnvironment(Paths $paths): void
    {
        LenientDotEnv::load(rtrim($paths->appDirectory, '\\/ ') . '/../');
    }

    /**
     * @return callable(string, mixed...): mixed
     */
    private static function invoker(): callable
    {
        $boot = new ReflectionClass(Boot::class);

        return static function (string $method, mixed ...$args) use ($boot): mixed {
            $ref = $boot->getMethod($method);
            $ref->setAccessible(true);

            return $ref->invoke(null, ...$args);
        };
    }
}
