<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\PHPStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPUnit\Framework\Attributes\Test;

/**
 * PHPStan extension that ensures proper return types for methods called
 * within inline test contexts.
 */
final class InlineTestTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        // This applies to any class - we check for #[Test] methods in isMethodSupported
        return \stdClass::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        // Check if we're inside a method with #[Test] attribute
        $declaringClass = $methodReflection->getDeclaringClass();
        $nativeReflection = $declaringClass->getNativeReflection();

        foreach ($nativeReflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Test::class);

            if (!empty($attributes)) {
                return true;
            }
        }

        return false;
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {
        // Return the default return type from the method reflection
        $variants = $methodReflection->getVariants();

        if (empty($variants)) {
            throw new \LogicException('Method has no variants');
        }

        return $variants[0]->getReturnType();
    }
}
