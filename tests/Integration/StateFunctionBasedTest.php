<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use NSRosenqvist\PHPUnitInline\TestCase\DynamicTestCaseGenerator;
use PHPUnit\Framework\TestCase;

final class StateFunctionBasedTest extends TestCase
{
    public function testGeneratedTestCaseHasStateSupport(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan();

        $stateTests = array_filter($testClasses, function ($tc) {
            return str_contains($tc->getClassName(), 'StateFunctionBased');
        });

        $stateTestClass = reset($stateTests);
        $this->assertNotFalse($stateTestClass);

        $generator = new DynamicTestCaseGenerator();
        $generatedClassName = $generator->generate($stateTestClass);

        // Verify the class has state methods
        $this->assertTrue(method_exists($generatedClassName, 'getState'));
        $this->assertTrue(method_exists($generatedClassName, 'setState'));
    }
}
