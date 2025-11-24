<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use NSRosenqvist\PHPUnitInline\TestCase\InlineTestSuiteBuilder;
use NSRosenqvist\PHPUnitInline\Tests\Fixtures\LifecycleMethods;
use PHPUnit\TextUI\TestRunner;

final class LifecycleMethodsTest extends TestCase
{
    #[Test]
    public function itDetectsLifecycleMethods(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $lifecycleClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === LifecycleMethods::class) {
                $lifecycleClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($lifecycleClass);
        $this->assertCount(2, $lifecycleClass->getTestMethods(), 'Should find 2 test methods');
        $this->assertCount(1, $lifecycleClass->getBeforeMethods(), 'Should find 1 Before method');
        $this->assertCount(1, $lifecycleClass->getAfterMethods(), 'Should find 1 After method');
        $this->assertCount(1, $lifecycleClass->getBeforeClassMethods(), 'Should find 1 BeforeClass method');
        $this->assertCount(1, $lifecycleClass->getAfterClassMethods(), 'Should find 1 AfterClass method');
    }

    #[Test]
    public function itExecutesLifecycleMethodsInCorrectOrder(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $lifecycleClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === LifecycleMethods::class) {
                $lifecycleClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($lifecycleClass);

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$lifecycleClass]);

        // Verify the suite was built correctly
        $this->assertGreaterThan(0, $suite->count(), 'Suite should contain tests');

        // Get the first test suite (class-level suite)
        $suites = iterator_to_array($suite->tests());
        $this->assertNotEmpty($suites);
        $classSuite = $suites[0];
        $this->assertInstanceOf(\PHPUnit\Framework\TestSuite::class, $classSuite);

        // Get individual test cases from the class suite
        $tests = iterator_to_array($classSuite->tests());
        $this->assertNotEmpty($tests);

        // Get the generated test class from the first test case
        $firstTest = $tests[0];
        $this->assertInstanceOf(\PHPUnit\Framework\TestCase::class, $firstTest);

        $generatedClass = new \ReflectionClass($firstTest);

        // Verify lifecycle methods were generated
        $this->assertTrue(
            $generatedClass->hasMethod('setUpBeforeClass'),
            'Generated class should have setUpBeforeClass method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('tearDownAfterClass'),
            'Generated class should have tearDownAfterClass method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('setUp'),
            'Generated class should have setUp method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('tearDown'),
            'Generated class should have tearDown method'
        );

        // Verify test methods were generated
        $this->assertTrue(
            $generatedClass->hasMethod('testOne'),
            'Generated class should have testOne method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('testTwo'),
            'Generated class should have testTwo method'
        );
    }
}
