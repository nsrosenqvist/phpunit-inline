<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Integration;

use Acme\Service\Tests\EmailServiceTests;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\InlineTestSuiteBuilder;

final class NamespaceLifecycleTest extends TestCase
{
    #[Test]
    public function itDetectsLifecycleMethodsInNamespaceBasedTests(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $emailServiceTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === EmailServiceTests::class) {
                $emailServiceTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($emailServiceTests);
        $this->assertCount(2, $emailServiceTests->getTestMethods());
        $this->assertCount(1, $emailServiceTests->getBeforeMethods());
        $this->assertCount(1, $emailServiceTests->getAfterMethods());
        $this->assertCount(1, $emailServiceTests->getBeforeClassMethods());
        $this->assertCount(1, $emailServiceTests->getAfterClassMethods());
    }

    #[Test]
    public function itExecutesNamespaceLifecycleMethodsInCorrectOrder(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $emailServiceTests = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === EmailServiceTests::class) {
                $emailServiceTests = $testClass;
                break;
            }
        }

        $this->assertNotNull($emailServiceTests);

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$emailServiceTests]);

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

        // Verify lifecycle methods were generated for namespace-based tests
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
            $generatedClass->hasMethod('itSendsEmails'),
            'Generated class should have itSendsEmails method'
        );
        $this->assertTrue(
            $generatedClass->hasMethod('itStartsWithEmptyList'),
            'Generated class should have itStartsWithEmptyList method'
        );
    }
}
