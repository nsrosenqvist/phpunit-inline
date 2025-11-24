<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\PHPStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PHPStan extension that provides TestCase methods to classes with #[Test] methods.
 * This allows inline test methods to call PHPUnit assertions without errors.
 */
final class InlineTestMethodReflectionExtension implements MethodsClassReflectionExtension
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider
    ) {
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        // Only apply to classes that have at least one #[Test] method
        if (!$this->hasTestMethods($classReflection)) {
            return false;
        }

        // Check if the method exists on TestCase
        if (!$this->reflectionProvider->hasClass(TestCase::class)) {
            return false;
        }

        $testCaseReflection = $this->reflectionProvider->getClass(TestCase::class);

        return $testCaseReflection->hasMethod($methodName);
    }

    public function getMethod(
        ClassReflection $classReflection,
        string $methodName
    ): MethodReflection {
        $testCaseReflection = $this->reflectionProvider->getClass(TestCase::class);

        return $testCaseReflection->getNativeMethod($methodName);
    }

    private function hasTestMethods(ClassReflection $classReflection): bool
    {
        $nativeReflection = $classReflection->getNativeReflection();

        foreach ($nativeReflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Test::class);

            if (!empty($attributes)) {
                return true;
            }
        }

        return false;
    }
}
