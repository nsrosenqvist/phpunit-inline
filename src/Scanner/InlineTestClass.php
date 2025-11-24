<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Scanner;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Represents a class or namespace containing inline test methods/functions.
 */
final class InlineTestClass
{
    /**
     * @param ReflectionClass<object>|null $reflection Class reflection, null for function-only tests
     * @param array<ReflectionMethod|ReflectionFunction> $testMethods
     * @param array<ReflectionMethod|ReflectionFunction> $beforeMethods
     * @param array<ReflectionMethod|ReflectionFunction> $afterMethods
     * @param array<ReflectionMethod|ReflectionFunction> $beforeClassMethods
     * @param array<ReflectionMethod|ReflectionFunction> $afterClassMethods
     * @param string|null $namespace For function-only tests, the namespace they belong to
     */
    public function __construct(
        private readonly ?ReflectionClass $reflection,
        private readonly array $testMethods,
        private readonly array $beforeMethods = [],
        private readonly array $afterMethods = [],
        private readonly array $beforeClassMethods = [],
        private readonly array $afterClassMethods = [],
        private readonly ?string $namespace = null
    ) {
    }

    /**
     * @return ReflectionClass<object>|null
     */
    public function getReflection(): ?ReflectionClass
    {
        return $this->reflection;
    }

    /**
     * @return array<ReflectionMethod|ReflectionFunction>
     */
    public function getTestMethods(): array
    {
        return $this->testMethods;
    }

    public function getClassName(): string
    {
        if ($this->reflection !== null) {
            return $this->reflection->getName();
        }

        // For function-only tests, generate a class name from the namespace
        return ($this->namespace ?? 'Global') . '\\Tests';
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function isFunctionBased(): bool
    {
        return $this->reflection === null;
    }

    /**
     * @return array<ReflectionMethod|ReflectionFunction>
     */
    public function getBeforeMethods(): array
    {
        return $this->beforeMethods;
    }

    /**
     * @return array<ReflectionMethod|ReflectionFunction>
     */
    public function getAfterMethods(): array
    {
        return $this->afterMethods;
    }

    /**
     * @return array<ReflectionMethod|ReflectionFunction>
     */
    public function getBeforeClassMethods(): array
    {
        return $this->beforeClassMethods;
    }

    /**
     * @return array<ReflectionMethod|ReflectionFunction>
     */
    public function getAfterClassMethods(): array
    {
        return $this->afterClassMethods;
    }
}
