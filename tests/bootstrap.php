<?php

declare(strict_types=1);

/**
 * Bootstrap file for phpunit-inline tests.
 * This extends the main bootstrap to add test fixture namespaces.
 */

// Load the main bootstrap
require_once __DIR__ . '/../src/bootstrap.php';

// Add test fixture namespaces manually for our own tests
use NSRosenqvist\PHPUnitInline\Autoloader\InlineTestAutoloader;

// Get the registered autoloader and add our test fixtures
$autoloader = new InlineTestAutoloader();
$autoloader->addNamespace('Acme\\', __DIR__ . '/Fixtures');
$autoloader->register();
