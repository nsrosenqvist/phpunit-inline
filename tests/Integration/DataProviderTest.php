<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\InlineTestSuiteBuilder;
use PHPUnit\InlineTests\Tests\Fixtures\DataProvider;

final class DataProviderTest extends TestCase
{
    #[Test]
    public function itDetectsDataProviderAttributes(): void
    {
        $reflection = new \ReflectionClass(DataProvider::class);
        $testAddition = $reflection->getMethod('testAddition');

        $scanner = new InlineTestScanner([]);
        $providerName = $scanner->findDataProvider($testAddition);

        $this->assertSame('additionProvider', $providerName);
    }

    #[Test]
    public function itReturnsNullForMethodsWithoutDataProvider(): void
    {
        $reflection = new \ReflectionClass(DataProvider::class);
        $testWithout = $reflection->getMethod('testWithoutDataProvider');

        $scanner = new InlineTestScanner([]);
        $providerName = $scanner->findDataProvider($testWithout);

        $this->assertNull($providerName);
    }

    #[Test]
    public function itCreatesMultipleTestCasesForDataProvider(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        // Find the DataProvider class
        $dataProviderClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === DataProvider::class) {
                $dataProviderClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($dataProviderClass, 'DataProvider should be discovered');

        // Build the test suite
        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$dataProviderClass]);

        // The suite should contain multiple test cases
        // testAddition has 4 data sets
        // testMultiplication has 4 data sets
        // testWithoutDataProvider has 1 test
        // testWithPrivateStaticProvider has 2 data sets
        // testWithPrivateInstanceProvider has 2 data sets
        // Total: 13 tests
        $this->assertGreaterThanOrEqual(13, $suite->count(), 'Should have at least 13 test cases');
    }

    #[Test]
    public function itSupportsPrivateDataProviders(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $dataProviderClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === DataProvider::class) {
                $dataProviderClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($dataProviderClass, 'DataProvider should be discovered');

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$dataProviderClass]);

        // Should include tests from private data providers
        $this->assertGreaterThanOrEqual(4, $suite->count());
    }

    #[Test]
    public function dataProviderTestsExecuteSuccessfully(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $dataProviderClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === DataProvider::class) {
                $dataProviderClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($dataProviderClass, 'DataProvider should be discovered');

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$dataProviderClass]);

        // Get the generated test suite
        $suites = iterator_to_array($suite->tests());
        $this->assertNotEmpty($suites);
        $classSuite = $suites[0];
        $this->assertInstanceOf(\PHPUnit\Framework\TestSuite::class, $classSuite);

        // Get individual test cases/suites from the class suite
        // Note: With data providers, PHPUnit creates DataProviderTestSuite objects
        $tests = iterator_to_array($classSuite->tests());
        $this->assertNotEmpty($tests);

        // Find a regular TestCase (not a DataProviderTestSuite)
        $testCase = null;
        foreach ($tests as $test) {
            if ($test instanceof \PHPUnit\Framework\TestCase) {
                $testCase = $test;
                break;
            }
            // If it's a DataProviderTestSuite, get the first test from it
            if ($test instanceof \PHPUnit\Framework\TestSuite) {
                $subTests = iterator_to_array($test->tests());
                if (!empty($subTests) && $subTests[0] instanceof \PHPUnit\Framework\TestCase) {
                    $testCase = $subTests[0];
                    break;
                }
            }
        }

        $this->assertNotNull($testCase, 'Should find at least one TestCase');
        $this->assertInstanceOf(\PHPUnit\Framework\TestCase::class, $testCase);

        $generatedClass = new \ReflectionClass($testCase);

        // Verify data provider methods were generated
        $this->assertTrue(
            $generatedClass->hasMethod('additionProvider'),
            'Generated class should have additionProvider method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('multiplicationProvider'),
            'Generated class should have multiplicationProvider method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('privateStaticProvider'),
            'Generated class should have privateStaticProvider method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('privateInstanceProvider'),
            'Generated class should have privateInstanceProvider method'
        );

        // Verify data provider methods are static
        $additionProvider = $generatedClass->getMethod('additionProvider');
        $this->assertTrue($additionProvider->isStatic(), 'Data provider should be static');
        $this->assertTrue($additionProvider->isPublic(), 'Data provider should be public');

        // Verify test methods have DataProvider attributes
        $testAddition = $generatedClass->getMethod('testAddition');
        $attributes = $testAddition->getAttributes(\PHPUnit\Framework\Attributes\DataProvider::class);
        $this->assertNotEmpty($attributes, 'testAddition should have DataProvider attribute');
    }
}
