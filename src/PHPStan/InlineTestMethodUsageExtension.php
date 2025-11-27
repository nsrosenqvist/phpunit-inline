<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\PHPStan;

use NSRosenqvist\PHPUnitInline\Attributes\DefaultFactory;
use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Methods\AlwaysUsedMethodExtension;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * PHPStan extension that marks methods with inline test attributes as "always used".
 *
 * This prevents PHPStan from reporting these methods as unused, since they are
 * invoked via reflection by the inline test runner.
 */
final class InlineTestMethodUsageExtension implements AlwaysUsedMethodExtension
{
    /**
     * Attributes that indicate a method is used via reflection.
     *
     * @var list<class-string>
     */
    private const USED_METHOD_ATTRIBUTES = [
        // PHPUnit attributes
        Test::class,
        Before::class,
        After::class,
        BeforeClass::class,
        AfterClass::class,
        DataProvider::class,
        // PHPUnit-Inline attributes
        State::class,
        DefaultFactory::class,
    ];

    public function isAlwaysUsed(MethodReflection $methodReflection): bool
    {
        $nativeReflection = $methodReflection->getDeclaringClass()->getNativeReflection();

        if (!$nativeReflection->hasMethod($methodReflection->getName())) {
            return false;
        }

        $method = $nativeReflection->getMethod($methodReflection->getName());

        foreach (self::USED_METHOD_ATTRIBUTES as $attributeClass) {
            if (!empty($method->getAttributes($attributeClass))) {
                return true;
            }
        }

        // Also check if this method is referenced by a #[Factory] or #[DataProvider] attribute on another method
        // @phpstan-ignore argument.type (PHPStan's BetterReflection adapter extends ReflectionClass)
        if ($this->isReferencedByFactoryAttribute($nativeReflection, $methodReflection->getName())) {
            return true;
        }
        // @phpstan-ignore argument.type (PHPStan's BetterReflection adapter extends ReflectionClass)
        if ($this->isReferencedByDataProviderAttribute($nativeReflection, $methodReflection->getName())) {
            return true;
        }

        return false;
    }

    /**
     * Check if a method is referenced by a #[Factory('methodName')] attribute on another method.
     *
     * @param \ReflectionClass<object> $classReflection
     */
    private function isReferencedByFactoryAttribute(\ReflectionClass $classReflection, string $methodName): bool
    {
        foreach ($classReflection->getMethods() as $method) {
            $factoryAttributes = $method->getAttributes(\NSRosenqvist\PHPUnitInline\Attributes\Factory::class);

            foreach ($factoryAttributes as $attribute) {
                $args = $attribute->getArguments();
                $referencedMethod = $args[0] ?? $args['methodName'] ?? null;

                if ($referencedMethod === $methodName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a method is referenced by a #[DataProvider('methodName')] attribute on another method.
     *
     * @param \ReflectionClass<object> $classReflection
     */
    private function isReferencedByDataProviderAttribute(\ReflectionClass $classReflection, string $methodName): bool
    {
        foreach ($classReflection->getMethods() as $method) {
            $dataProviderAttributes = $method->getAttributes(DataProvider::class);

            foreach ($dataProviderAttributes as $attribute) {
                $args = $attribute->getArguments();
                $referencedMethod = $args[0] ?? $args['methodName'] ?? null;

                if ($referencedMethod === $methodName) {
                    return true;
                }
            }
        }

        return false;
    }
}
