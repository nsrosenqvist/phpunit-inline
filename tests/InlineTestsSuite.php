<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests;

use PHPUnit\Framework\TestSuite;
use NSRosenqvist\PHPUnitInline\TestCase\InlineTestLoader;

/**
 * This test file can be included in your test suite to automatically
 * discover and run all inline tests.
 *
 * Add this to your phpunit.xml:
 * <testsuite name="Inline Tests">
 *     <file>vendor/nsrosenqvist/phpunit-inline/tests/InlineTestsSuite.php</file>
 * </testsuite>
 */
final class InlineTestsSuite
{
    public static function suite(): TestSuite
    {
        return InlineTestLoader::suite();
    }
}
