<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Unit\Scanner;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use NSRosenqvist\PHPUnitInline\Tests\Fixtures\Calculator;
use NSRosenqvist\PHPUnitInline\Tests\Fixtures\DataProvider;
use NSRosenqvist\PHPUnitInline\Tests\Fixtures\LifecycleMethods;

final class InlineTestScannerTest extends TestCase
{
    #[Test]
    public function testScanFindsTestMethodsInFixtures(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
        $testClasses = $scanner->scan();

        $this->assertNotEmpty($testClasses);

        // Find the Calculator class
        $calculatorClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === Calculator::class) {
                $calculatorClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($calculatorClass, 'Calculator class should be found');
        $this->assertCount(5, $calculatorClass->getTestMethods(), 'Calculator should have 5 test methods');
    }

    #[Test]
    public function testScanReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $scanner = new InlineTestScanner(['/non/existent/directory']);
        $testClasses = $scanner->scan();

        $this->assertIsArray($testClasses);
        $this->assertEmpty($testClasses);
    }

    #[Test]
    public function testScanReturnsEmptyArrayForDirectoryWithoutTests(): void
    {
        // Create a temp directory with a PHP file without tests
        $tempDir = sys_get_temp_dir() . '/phpunit-inline-tests-' . uniqid();
        mkdir($tempDir);

        file_put_contents(
            $tempDir . '/NoTests.php',
            '<?php class NoTests { public function foo() {} }'
        );

        $scanner = new InlineTestScanner([$tempDir]);
        $testClasses = $scanner->scan();

        $this->assertEmpty($testClasses);

        // Cleanup
        unlink($tempDir . '/NoTests.php');
        rmdir($tempDir);
    }

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
    public function itDetectsTestsInNestedNamespace(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
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
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
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
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
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
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
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

    #[Test]
    public function itDetectsStateInitializerOnClass(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
        $testClasses = $scanner->scan();

        // Find the StateClassBased test class
        $stateTests = array_filter($testClasses, function ($tc) {
            return str_contains($tc->getClassName(), 'StateClassBased');
        });

        $this->assertNotEmpty($stateTests, 'Should find StateClassBased tests');

        $stateTestClass = reset($stateTests);
        $this->assertNotFalse($stateTestClass);
        $this->assertFalse($stateTestClass->isFunctionBased());

        // Check state initializer is detected
        $stateInitializer = $stateTestClass->getStateInitializer();
        $this->assertNotNull($stateInitializer, 'Should detect state initializer');
        $this->assertEquals('initTestState', $stateInitializer->getName());
    }

    #[Test]
    public function itDetectsStaticStateInitializer(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
        $testClasses = $scanner->scan();

        $stateTests = array_filter($testClasses, function ($tc) {
            return str_contains($tc->getClassName(), 'StateClassBased');
        });

        $stateTestClass = reset($stateTests);
        $this->assertNotFalse($stateTestClass);

        $stateInitializer = $stateTestClass->getStateInitializer();
        $this->assertNotNull($stateInitializer);
        $this->assertInstanceOf(\ReflectionMethod::class, $stateInitializer);
        $this->assertTrue(
            $stateInitializer->isStatic(),
            'State initializer method should be static'
        );
    }

    #[Test]
    public function itDetectsFunctionBasedStateInitializer(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
        $testClasses = $scanner->scan();

        // Find the StateFunctionBased test class
        $stateTests = array_filter($testClasses, function ($tc) {
            return str_contains($tc->getClassName(), 'StateFunctionBased');
        });

        $this->assertNotEmpty($stateTests, 'Should find StateFunctionBased tests');

        $stateTestClass = reset($stateTests);
        $this->assertNotFalse($stateTestClass);
        $this->assertTrue($stateTestClass->isFunctionBased());

        // Check state initializer is detected
        $stateInitializer = $stateTestClass->getStateInitializer();
        $this->assertNotNull($stateInitializer, 'Should detect state initializer');
        $this->assertInstanceOf(\ReflectionFunction::class, $stateInitializer);
        $this->assertEquals('initState', $stateInitializer->getShortName());
    }

    #[Test]
    public function itDetectsLifecycleMethods(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
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
}
