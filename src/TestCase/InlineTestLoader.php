<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\TestCase;

use PHPUnit\Framework\TestSuite;
use NSRosenqvist\PHPUnitInline\Extension\InlineTestExtension;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use PHPUnit\Runner\TestSuiteLoader;

/**
 * Custom test suite loader that discovers and loads inline tests.
 *
 * Note: This approach may need adjustment based on PHPUnit's architecture.
 * An alternative is to provide a custom TestSuite class that users can include.
 */
final class InlineTestLoader
{
    /**
     * Creates a test suite containing all discovered inline tests.
     */
    public static function suite(): TestSuite
    {
        $scanDirectories = InlineTestExtension::getScanDirectories();

        if (empty($scanDirectories)) {
            return TestSuite::empty('Inline Tests (No directories configured)');
        }

        $scanner = new InlineTestScanner($scanDirectories);
        $testClasses = $scanner->scan();

        $builder = new InlineTestSuiteBuilder();

        return $builder->build($testClasses);
    }
}
