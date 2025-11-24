<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\InlineTestSuiteBuilder;

final class FunctionBasedTestsTest extends TestCase
{
    #[Test]
    public function itDetectsFunctionBasedTests(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        // Debug: show all discovered tests
        $namespaces = [];
        foreach ($testClasses as $tc) {
            $ns = $tc->getNamespace();
            if ($ns !== null) {
                $namespaces[] = $ns;
            }
        }

        // Find function-based tests in Acme\Math\Tests namespace
        $functionTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getNamespace() === 'Acme\Math\Tests') {
                $functionTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($functionTests, 'Function-based tests should be discovered. Found namespaces: ' . implode(', ', $namespaces));
        $this->assertNull($functionTests->getReflection(), 'Function-based tests should not have a class reflection');
        $this->assertGreaterThanOrEqual(3, count($functionTests->getTestMethods()), 'Should find at least 3 test functions');
    }

    #[Test]
    public function itBuildsSuiteFromFunctionBasedTests(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $functionTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getNamespace() === 'Acme\\Math\\Tests') {
                $functionTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($functionTests);

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$functionTests]);

        $this->assertGreaterThan(0, $suite->count(), 'Suite should contain tests');
    }

    #[Test]
    public function itGeneratesTestCaseClassForFunctions(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $functionTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getNamespace() === 'Acme\\Math\\Tests') {
                $functionTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($functionTests);

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$functionTests]);

        // Get the generated test suite
        $suites = iterator_to_array($suite->tests());
        $this->assertNotEmpty($suites);
        $classSuite = $suites[0];
        $this->assertInstanceOf(\PHPUnit\Framework\TestSuite::class, $classSuite);

        // Get individual test cases
        $tests = iterator_to_array($classSuite->tests());
        $this->assertNotEmpty($tests);

        $firstTest = $tests[0];
        $this->assertInstanceOf(\PHPUnit\Framework\TestCase::class, $firstTest);

        $generatedClass = new \ReflectionClass($firstTest);

        // Verify test methods were generated from functions
        $this->assertTrue(
            $generatedClass->hasMethod('testAdd'),
            'Generated class should have testAdd method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('testMultiply'),
            'Generated class should have testMultiply method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('testAddNegative'),
            'Generated class should have testAddNegative method'
        );
    }
}
