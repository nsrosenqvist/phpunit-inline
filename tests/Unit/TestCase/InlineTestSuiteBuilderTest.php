<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Unit\TestCase;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestClass;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use NSRosenqvist\PHPUnitInline\TestCase\InlineTestSuiteBuilder;
use NSRosenqvist\PHPUnitInline\Tests\Fixtures\Calculator;

final class InlineTestSuiteBuilderTest extends TestCase
{
    #[Test]
    public function testBuildCreatesTestSuiteFromInlineTestClasses(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
        $testClasses = $scanner->scan();

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build($testClasses);

        $this->assertEquals('Inline Tests', $suite->name());
        $this->assertGreaterThan(0, $suite->count());
    }

    #[Test]
    public function testBuildCreatesTestCasesForEachTestMethod(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
        $testClasses = $scanner->scan();

        // Find Calculator
        $calculatorClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === Calculator::class) {
                $calculatorClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($calculatorClass);

        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$calculatorClass]);

        // The suite should contain a sub-suite for Calculator
        $this->assertGreaterThan(0, $suite->count());
    }

    #[Test]
    public function testBuildHandlesEmptyTestClassArray(): void
    {
        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([]);

        $this->assertEquals('Inline Tests', $suite->name());
        $this->assertEquals(0, $suite->count());
    }
}
