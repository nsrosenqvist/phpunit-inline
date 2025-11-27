<?php

declare(strict_types=1);

// Load the main package's autoloader
require_once __DIR__ . '/../../../vendor/autoload.php';

// Register autoloader for E2E test classes
spl_autoload_register(function (string $class): void {
    $prefix = 'E2E\\';
    $baseDir = __DIR__ . '/src/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load the functions file
require_once __DIR__ . '/src/functions.php';

// Register the inline test autoloader for namespace-based tests
use NSRosenqvist\PHPUnitInline\Autoloader\InlineTestAutoloader;

$autoloader = new InlineTestAutoloader(['E2E' => __DIR__ . '/src']);
$autoloader->register();
