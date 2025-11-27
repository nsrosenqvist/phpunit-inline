<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\PHPStan;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

/**
 * PHPStan extension that infers the return type of state() based on the
 * #[State] attributed function/method in the same scope.
 */
final class StateReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'state';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope
    ): Type {
        // Try to find the state initializer in the current context
        $stateType = $this->findStateTypeInClass($scope);

        if ($stateType !== null) {
            return $stateType;
        }

        $stateType = $this->findStateTypeInNamespace($scope);

        if ($stateType !== null) {
            return $stateType;
        }

        return new MixedType();
    }

    /**
     * Find state initializer in a class (for class-based inline tests).
     */
    private function findStateTypeInClass(Scope $scope): ?Type
    {
        if (!$scope->isInClass()) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        $nativeReflection = $classReflection->getNativeReflection();

        foreach ($nativeReflection->getMethods() as $method) {
            $attributes = $method->getAttributes(State::class);

            if (!empty($attributes)) {
                // Get the return type from the method
                $returnType = $method->getReturnType();

                if ($returnType instanceof \ReflectionNamedType) {
                    $typeName = $returnType->getName();
                    return $this->resolveTypeName($typeName, $classReflection->getName(), $scope->getFile());
                }

                break;
            }
        }

        return null;
    }

    /**
     * Find state initializer function in the current file's namespace (for function-based tests).
     */
    private function findStateTypeInNamespace(Scope $scope): ?Type
    {
        $namespace = $scope->getNamespace();
        $file = $scope->getFile();

        // Parse the file to find functions with #[State] attribute
        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        // Find #[State] attributed functions in the file
        // Look for pattern: #[State] followed by function declaration with return type
        $pattern = '/#\[State\]\s*(?:\/\*.*?\*\/\s*)?function\s+(\w+)\s*\([^)]*\)\s*:\s*([^\s{]+)/s';

        if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $returnTypeName = trim($match[2]);

                // Resolve the type name in the current namespace context
                return $this->resolveTypeName($returnTypeName, $namespace, $file);
            }
        }

        return null;
    }

    /**
     * Resolve a type name to a PHPStan Type.
     */
    private function resolveTypeName(string $typeName, ?string $namespace, string $file): ?Type
    {
        // Handle built-in types
        $builtinTypes = [
            'int' => new \PHPStan\Type\IntegerType(),
            'string' => new \PHPStan\Type\StringType(),
            'bool' => new \PHPStan\Type\BooleanType(),
            'float' => new \PHPStan\Type\FloatType(),
            'array' => new \PHPStan\Type\ArrayType(new MixedType(), new MixedType()),
            'object' => new \PHPStan\Type\ObjectWithoutClassType(),
            'mixed' => new MixedType(),
            'void' => new \PHPStan\Type\VoidType(),
            'null' => new \PHPStan\Type\NullType(),
            'true' => new \PHPStan\Type\Constant\ConstantBooleanType(true),
            'false' => new \PHPStan\Type\Constant\ConstantBooleanType(false),
        ];

        $lowerTypeName = strtolower($typeName);
        if (isset($builtinTypes[$lowerTypeName])) {
            return $builtinTypes[$lowerTypeName];
        }

        // Try to resolve as a class name
        $className = $this->resolveClassName($typeName, $namespace, $file);

        if ($className !== null && (class_exists($className) || interface_exists($className))) {
            return new \PHPStan\Type\ObjectType($className);
        }

        return null;
    }

    /**
     * Resolve a class name considering use statements and namespace.
     */
    private function resolveClassName(string $typeName, ?string $namespace, string $file): ?string
    {
        // If it's already fully qualified
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        // Parse file for use statements
        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        // Find use statements
        $useStatements = [];
        if (preg_match_all('/use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fullClass = $match[1];
                $alias = $match[2] ?? basename(str_replace('\\', '/', $fullClass));
                $useStatements[$alias] = $fullClass;
            }
        }

        // Check if the type matches a use statement
        if (isset($useStatements[$typeName])) {
            return $useStatements[$typeName];
        }

        // Check if it's a class in the same namespace
        if ($namespace !== null) {
            $fullyQualified = $namespace . '\\' . $typeName;
            if (class_exists($fullyQualified) || interface_exists($fullyQualified)) {
                return $fullyQualified;
            }
        }

        // Try as a global class
        if (class_exists($typeName) || interface_exists($typeName)) {
            return $typeName;
        }

        // For classes defined in the same file (like TestState), check if the class exists in the file
        $classPattern = '/(?:^|\s)(?:class|interface)\s+' . preg_quote($typeName, '/') . '\b/m';
        if (preg_match($classPattern, $contents)) {
            // Class is defined in this file, return with namespace
            return $namespace !== null ? $namespace . '\\' . $typeName : $typeName;
        }

        return null;
    }
}
