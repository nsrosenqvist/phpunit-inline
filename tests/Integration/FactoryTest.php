<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use NSRosenqvist\PHPUnitInline\TestCase\InlineTestSuiteBuilder;

final class FactoryTest extends TestCase
{
    #[Test]
    public function itDetectsTestsInClassWithConstructorArguments(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $factoryTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === 'NSRosenqvist\PHPUnitInline\Tests\Fixtures\FactoryExample') {
                $factoryTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($factoryTests, 'Should find FactoryExample class');
        $this->assertCount(4, $factoryTests->getTestMethods(), 'Should find 4 test methods');
    }

    #[Test]
    public function itExecutesTestsWithDefaultFactory(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $factoryTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === 'NSRosenqvist\PHPUnitInline\Tests\Fixtures\FactoryExample') {
                $factoryTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($factoryTests);

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$factoryTests]);

        // Verify suite was built with tests
        $this->assertGreaterThan(0, $suite->count(), 'Suite should contain tests');

        // Get the generated test class and verify it has the right methods
        $suites = iterator_to_array($suite->tests());
        $this->assertNotEmpty($suites);

        /** @var \PHPUnit\Framework\TestSuite $testSuite */
        $testSuite = $suites[0];
        $tests = iterator_to_array($testSuite->tests());
        $this->assertCount(4, $tests, 'Should have 4 test cases');

        // Run one of the tests to verify factory works
        $firstTest = $tests[0];
        $this->assertInstanceOf(TestCase::class, $firstTest);

        // The test should pass without throwing exceptions
        $firstTest->runBare();
    }
}
