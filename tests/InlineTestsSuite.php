<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests;

use PHPUnit\Framework\TestSuite;
use PHPUnit\InlineTests\TestCase\InlineTestLoader;

/**
 * This test file can be included in your test suite to automatically
 * discover and run all inline tests.
 *
 * Add this to your phpunit.xml:
 * <testsuite name="Inline Tests">
 *     <file>vendor/phpunit/inline-tests/tests/InlineTestsSuite.php</file>
 * </testsuite>
 */
final class InlineTestsSuite
{
    public static function suite(): TestSuite
    {
        return InlineTestLoader::suite();
    }
}
