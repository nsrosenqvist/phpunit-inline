<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Scanner;

use ReflectionFunction;

/**
 * Represents a standalone function with inline test attributes.
 */
final class InlineTestFunction
{
    /**
     * @param ReflectionFunction $reflection
     * @param string $namespace The namespace the function belongs to
     */
    public function __construct(
        private readonly ReflectionFunction $reflection,
        private readonly string $namespace
    ) {
    }

    public function getReflection(): ReflectionFunction
    {
        return $this->reflection;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->reflection->getName();
    }
}
