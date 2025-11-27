<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\PHPStan;

use NSRosenqvist\PHPUnitInline\TestCaseProxy;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * PHPStan extension that provides the return type for the test() helper function.
 *
 * The test() function returns a TestCaseProxy instance that provides access
 * to PHPUnit TestCase methods like assertions, mocking, etc.
 */
final class TestFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        $name = $functionReflection->getName();

        return $name === 'test' || $name === 'NSRosenqvist\\PHPUnitInline\\test';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope
    ): Type {
        return new ObjectType(TestCaseProxy::class);
    }
}
