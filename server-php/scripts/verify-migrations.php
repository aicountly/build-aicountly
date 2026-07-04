<?php

/**
 * Local sanity check: instantiate every migration class to verify class name,
 * namespace, and constructor requirements match the file name. Does NOT
 * touch the database.
 */

require __DIR__ . '/../vendor/autoload.php';

$dir = __DIR__ . '/../app/Database/Migrations';
$files = glob($dir . '/*.php');
sort($files);

$errors = [];
$count  = 0;

foreach ($files as $file) {
    $count++;
    $name = basename($file, '.php');
    require_once $file;

    if (! preg_match('/^\d{4}-\d{2}-\d{2}-\d{6}_(\w+)$/', $name, $m)) {
        $errors[] = "$name: does not match CI4 migration naming.";
        continue;
    }

    $class    = 'App\\Database\\Migrations\\' . $m[1];
    if (! class_exists($class)) {
        $errors[] = "$name: expected class $class not found.";
        continue;
    }

    $refl = new ReflectionClass($class);
    if (! $refl->isSubclassOf('CodeIgniter\\Database\\Migration')) {
        $errors[] = "$name: $class must extend CodeIgniter\\Database\\Migration.";
        continue;
    }

    foreach (['up', 'down'] as $method) {
        if (! $refl->hasMethod($method)) {
            $errors[] = "$name: missing $method() method.";
        }
    }
}

if ($errors) {
    echo "Found errors in $count migration files:\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
    exit(1);
}

echo "OK: $count migration files parse cleanly and expose up()/down().\n";
