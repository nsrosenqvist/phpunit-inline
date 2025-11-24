<?php

declare(strict_types=1);

/**
 * PHPUnit Inline Tests Bootstrap
 *
 * This file registers the custom autoloader for inline test classes.
 * Include this in your phpunit.xml bootstrap attribute:
 *
 * <phpunit bootstrap="vendor/phpunit/inline-tests/src/bootstrap.php">
 *     ...
 * </phpunit>
 *
 * Or register manually in your own bootstrap file:
 *
 * require_once 'vendor/autoload.php';
 *
 * $autoloader = PHPUnit\InlineTests\Autoloader\InlineTestAutoloader::fromComposerJson(__DIR__ . '/composer.json');
 * $autoloader->register();
 */

// First, load Composer's autoloader
$dir = __DIR__;
$vendorAutoload = null;

for ($i = 0; $i < 10; $i++) {
    $dir = dirname($dir);
    $candidatePath = $dir . '/vendor/autoload.php';

    if (is_file($candidatePath)) {
        $vendorAutoload = $candidatePath;
        break;
    }
}

if ($vendorAutoload !== null) {
    require_once $vendorAutoload;
}

use PHPUnit\InlineTests\Autoloader\InlineTestAutoloader;

// Find the project's composer.json by walking up the directory tree
$dir = __DIR__;
$composerJsonPath = null;

for ($i = 0; $i < 10; $i++) {
    $dir = dirname($dir);
    $candidatePath = $dir . '/composer.json';

    if (is_file($candidatePath)) {
        $composerJsonPath = $candidatePath;
        break;
    }
}

if ($composerJsonPath !== null) {
    $autoloader = InlineTestAutoloader::fromComposerJson($composerJsonPath);
    $autoloader->register();
}
