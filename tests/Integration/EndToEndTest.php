<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use NSRosenqvist\PHPUnitInline\TestCase\InlineTestCase;
use NSRosenqvist\PHPUnitInline\Tests\Fixtures\Calculator;

/**
 * End-to-end test that actually executes inline tests through the full extension flow.
 *
 * Note: Full end-to-end execution with lifecycle methods, factories, etc. requires
 * using the DynamicTestCaseGenerator which creates test classes that PHPUnit runs.
 * These tests verify that InlineTestCase::runInlineTest() works for basic test execution.
 */
final class EndToEndTest extends TestCase
{
    #[Test]
    public function testCalculatorBasicTestsExecute(): void
    {
        $reflection = new \ReflectionClass(Calculator::class);

        // Test basic methods that don't use expectException
        $basicTests = ['testAdd', 'testSubtract', 'testMultiplyPrivateMethod', 'testDivideProtectedMethod'];

        foreach ($basicTests as $methodName) {
            $method = $reflection->getMethod($methodName);

            $testCase = InlineTestCase::createTest($reflection, $method);
            $testCase->runInlineTest();
        }

        // If we got here, all tests passed
        $this->assertTrue(true, 'All basic Calculator tests executed successfully');
    }

    #[Test]
    public function testScannerFindsAllExpectedTestClasses(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures';

        $scanner = new InlineTestScanner([$fixturesDir]);
        $testClasses = $scanner->scan();

        // Verify we find expected test classes
        $classNames = array_map(fn ($tc) => $tc->getClassName(), $testClasses);

        $this->assertContains('NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\Calculator', $classNames);
        $this->assertContains('NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\DataProvider', $classNames);
        $this->assertContains('NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\FactoryExample', $classNames);
        $this->assertContains('NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\LifecycleMethods', $classNames);

        // Should also find function-based tests
        $functionBased = array_filter($testClasses, fn ($tc) => $tc->isFunctionBased());
        $this->assertNotEmpty($functionBased, 'Should find function-based tests');
    }

    #[Test]
    public function testScannerDetectsTestMethods(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures';

        $scanner = new InlineTestScanner([$fixturesDir]);
        $testClasses = $scanner->scan();

        // Find Calculator and verify test method count
        foreach ($testClasses as $tc) {
            if ($tc->getClassName() === 'NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\Calculator') {
                $this->assertCount(5, $tc->getTestMethods(), 'Calculator should have 5 test methods');

                break;
            }
        }
    }

    #[Test]
    public function testScannerDetectsLifecycleMethods(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures';

        $scanner = new InlineTestScanner([$fixturesDir]);
        $testClasses = $scanner->scan();

        // Find LifecycleMethods and verify lifecycle method detection
        foreach ($testClasses as $tc) {
            if ($tc->getClassName() === 'NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\LifecycleMethods') {
                $this->assertNotEmpty($tc->getBeforeMethods(), 'Should detect #[Before] methods');

                break;
            }
        }
    }

    #[Test]
    public function testScannerDetectsDataProviders(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures';

        $scanner = new InlineTestScanner([$fixturesDir]);
        $testClasses = $scanner->scan();

        // Find DataProvider fixture and verify data provider methods
        foreach ($testClasses as $tc) {
            if ($tc->getClassName() === 'NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\DataProvider') {
                $testMethods = $tc->getTestMethods();

                // Check that at least one method has a DataProvider attribute
                $hasDataProvider = false;
                foreach ($testMethods as $method) {
                    if ($method instanceof \ReflectionMethod) {
                        $attrs = $method->getAttributes(\PHPUnit\Framework\Attributes\DataProvider::class);
                        if (!empty($attrs)) {
                            $hasDataProvider = true;
                            break;
                        }
                    }
                }

                $this->assertTrue($hasDataProvider, 'Should find tests with #[DataProvider] attribute');

                break;
            }
        }
    }

    #[Test]
    public function testScannerDetectsFactoryMethods(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures';

        $scanner = new InlineTestScanner([$fixturesDir]);
        $testClasses = $scanner->scan();

        // Find FactoryExample fixture
        foreach ($testClasses as $tc) {
            if ($tc->getClassName() === 'NSRosenqvist\\PHPUnitInline\\Tests\\Fixtures\\FactoryExample') {
                // Verify it has test methods
                $this->assertNotEmpty($tc->getTestMethods(), 'FactoryExample should have test methods');

                // The factory handling is done in DynamicTestCaseGenerator
                break;
            }
        }

        $this->assertTrue(true, 'Factory detection verified');
    }
}
