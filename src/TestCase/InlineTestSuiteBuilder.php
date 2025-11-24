<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\TestCase;

use PHPUnit\Framework\TestSuite;
use PHPUnit\InlineTests\Scanner\InlineTestClass;

/**
 * Builds PHPUnit TestSuite instances from inline test classes.
 */
final class InlineTestSuiteBuilder
{
    private DynamicTestCaseGenerator $generator;

    public function __construct()
    {
        $this->generator = new DynamicTestCaseGenerator();
    }

    /**
     * Build a test suite from discovered inline test classes.
     *
     * @param array<InlineTestClass> $testClasses
     */
    public function build(array $testClasses): TestSuite
    {
        $suite = TestSuite::empty('Inline Tests');

        foreach ($testClasses as $testClass) {
            $className = $this->generator->generate($testClass);
            $reflection = new \ReflectionClass($className);
            $classSuite = TestSuite::fromClassReflector($reflection);
            $suite->addTest($classSuite);
        }

        return $suite;
    }
}
