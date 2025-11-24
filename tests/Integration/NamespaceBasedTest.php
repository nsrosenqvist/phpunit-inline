<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;

final class NamespaceBasedTest extends TestCase
{
    #[Test]
    public function itDetectsTestsInNestedNamespace(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        // Find the CalculatorTests class
        $calculatorTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === 'Acme\Calculator\Tests\CalculatorTests') {
                $calculatorTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($calculatorTests);
        $this->assertCount(3, $calculatorTests->getTestMethods());
    }

    #[Test]
    public function itSkipsTestCaseSubclassesInTestsNamespace(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        // ServiceTest extends TestCase, so it should NOT be in the results
        foreach ($testClasses as $testClass) {
            $this->assertNotSame(
                'Acme\Service\Tests\ServiceTest',
                $testClass->getClassName(),
                'ServiceTest extends TestCase and should be skipped'
            );
        }

        // Test passed if we got here - ServiceTest was not found
        $this->assertTrue(true);
    }

    #[Test]
    public function itExtractsBothProductionAndTestClasses(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        // Find classes from the NamespaceBasedTests.php file
        $foundCalculator = false;
        $foundCalculatorTests = false;

        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === 'Acme\Calculator\Calculator') {
                $foundCalculator = true;
            }
            if ($testClass->getClassName() === 'Acme\Calculator\Tests\CalculatorTests') {
                $foundCalculatorTests = true;
            }
        }

        // Calculator class has no #[Test] methods, so it should NOT be found
        $this->assertFalse($foundCalculator, 'Calculator has no test methods');

        // CalculatorTests has #[Test] methods, so it SHOULD be found
        $this->assertTrue($foundCalculatorTests, 'CalculatorTests should be found');
    }

    #[Test]
    public function itSupportsDataProvidersInNamespaceBasedTests(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        // Find the CalculatorTests class
        $calculatorTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === 'Acme\Calculator\Tests\CalculatorTests') {
                $calculatorTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($calculatorTests);

        $testMethods = $calculatorTests->getTestMethods();

        // Find the test method with data provider
        $dataProviderMethod = null;
        foreach ($testMethods as $method) {
            if ($method->getName() === 'itAddsWithDataProvider') {
                $dataProviderMethod = $method;
                break;
            }
        }

        $this->assertNotNull($dataProviderMethod);

        // Check for DataProvider attribute
        $attributes = $dataProviderMethod->getAttributes(
            \PHPUnit\Framework\Attributes\DataProvider::class
        );

        $this->assertNotEmpty($attributes);
    }
}
