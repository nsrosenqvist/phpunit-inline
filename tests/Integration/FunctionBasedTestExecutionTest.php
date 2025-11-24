<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\InlineTestSuiteBuilder;
use PHPUnit\TextUI\TestRunner;

/**
 * Tests that function-based tests can actually execute and verify behavior.
 */
final class FunctionBasedTestExecutionTest extends TestCase
{
    public function testFunctionBasedTestsCanExecute(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan(__DIR__ . '/../Fixtures/FunctionBasedTests.php');

        // Find the function-based test class
        $testClass = null;
        foreach ($testClasses as $tc) {
            if ($tc->isFunctionBased() && $tc->getNamespace() === 'Acme\\Math\\Tests') {
                $testClass = $tc;
                break;
            }
        }

        self::assertNotNull($testClass, 'Should find function-based test class');

        // Build a suite from it
        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$testClass]);

        // Find the actual test suite for our function-based tests
        $tests = $suite->tests();
        self::assertCount(1, $tests, 'Should have 1 test suite');

        $functionSuite = $tests[0];
        self::assertCount(3, $functionSuite->tests(), 'Function suite should have 3 tests');

        // Verify the test names
        $testNames = [];
        foreach ($functionSuite->tests() as $test) {
            $testNames[] = $test->name();
        }

        self::assertContains('testAdd', $testNames, 'Should have testAdd');
        self::assertContains('testMultiply', $testNames, 'Should have testMultiply');
        self::assertContains('testAddNegative', $testNames, 'Should have testAddNegative');
    }
}
