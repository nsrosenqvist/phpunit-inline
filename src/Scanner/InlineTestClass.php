<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Scanner;

use ReflectionClass;
use ReflectionMethod;

/**
 * Represents a class containing inline test methods.
 */
final class InlineTestClass
{
    /**
     * @param ReflectionClass<object> $reflection
     * @param array<ReflectionMethod> $testMethods
     */
    public function __construct(
        private readonly ReflectionClass $reflection,
        private readonly array $testMethods
    ) {
    }

    /**
     * @return ReflectionClass<object>
     */
    public function getReflection(): ReflectionClass
    {
        return $this->reflection;
    }

    /**
     * @return array<ReflectionMethod>
     */
    public function getTestMethods(): array
    {
        return $this->testMethods;
    }

    public function getClassName(): string
    {
        return $this->reflection->getName();
    }
}
