<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Bootstrappers;

use NSRosenqvist\PHPUnitInline\TestSuite;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;

/**
 * Bootstraps test files by scanning configured directories for inline tests.
 *
 * When a file is loaded, any #[Test] functions are registered in the TestSuite.
 * This happens before PHPUnit's test discovery.
 *
 * @internal
 */
final class BootTestFiles
{
    /**
     * Boot test files from the configured scan directories.
     */
    public function boot(): void
    {
        $testSuite = TestSuite::getInstance();
        $scanDirectories = $testSuite->getScanDirectories();

        if (empty($scanDirectories)) {
            return;
        }

        $scanner = new InlineTestScanner($scanDirectories);
        $testClasses = $scanner->scan();

        // Register all discovered test classes with the TestSuite
        foreach ($testClasses as $testClass) {
            $testSuite->addTestClass($testClass);
        }
    }
}
