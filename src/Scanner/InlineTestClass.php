<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Scanner;

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
     * @param ReflectionMethod|ReflectionFunction|null $stateInitializer The #[State] function/method
     * @param string|null $namespace For function-only tests, the namespace they belong to
     * @param string|null $sourceFile The source file path (for filename-based test naming)
     */
    public function __construct(
        private readonly ?ReflectionClass $reflection,
        private readonly array $testMethods,
        private readonly array $beforeMethods = [],
        private readonly array $afterMethods = [],
        private readonly array $beforeClassMethods = [],
        private readonly array $afterClassMethods = [],
        private readonly ReflectionMethod|ReflectionFunction|null $stateInitializer = null,
        private readonly ?string $namespace = null,
        private readonly ?string $sourceFile = null
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

        // For function-only tests, generate a class name
        // If we have a source file, use the filename (e.g., helpers.php -> HelpersTest)
        if ($this->sourceFile !== null) {
            $basename = pathinfo($this->sourceFile, PATHINFO_FILENAME);
            $testClassName = ucfirst($basename) . 'Test';

            // Include namespace if present
            if ($this->namespace !== null && $this->namespace !== '') {
                return $this->namespace . '\\' . $testClassName;
            }

            return $testClassName;
        }

        // Fallback to namespace-based naming
        return ($this->namespace ?? 'Global') . '\\Tests';
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getSourceFile(): ?string
    {
        return $this->sourceFile;
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

    /**
     * Get the state initializer function/method (marked with #[State]).
     */
    public function getStateInitializer(): ReflectionMethod|ReflectionFunction|null
    {
        return $this->stateInitializer;
    }
}
