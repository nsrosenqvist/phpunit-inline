<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline;

use NSRosenqvist\PHPUnitInline\Bootstrappers\BootTestFiles;

/**
 * The Kernel orchestrates the boot process for inline tests.
 *
 * This must be called AFTER the override files are loaded but BEFORE PHPUnit runs.
 * The override files should be loaded before the autoloader to ensure
 * our TestSuiteLoader is used instead of PHPUnit's.
 */
final class Kernel
{
    /**
     * Boot the inline test system.
     *
     * @param string $rootPath The project root path
     * @param array<string> $scanDirectories Directories to scan for inline tests
     */
    public static function boot(string $rootPath, array $scanDirectories): void
    {
        // Initialize the TestSuite singleton with configuration
        $testSuite = TestSuite::getInstance($rootPath);
        $testSuite->setScanDirectories($scanDirectories);

        // Scan and register test files
        (new BootTestFiles())->boot();
    }
}
