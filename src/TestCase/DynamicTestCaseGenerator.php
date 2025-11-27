<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\TestCase;

use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestClass;
use NSRosenqvist\PHPUnitInline\Attributes\Factory;
use NSRosenqvist\PHPUnitInline\Attributes\DefaultFactory;
use ReflectionMethod;

/**
 * Generates a dynamic TestCase class from an InlineTestClass.
 * Uses eval() once to create a complete class with all test methods.
 */
final class DynamicTestCaseGenerator
{
    /**
     * Generate and register a TestCase class for the given InlineTestClass.
     *
     * @return class-string<TestCase>
     */
    public function generate(InlineTestClass $testClass): string
    {
        $className = $this->generateClassName($testClass->getClassName());

        // Don't regenerate if class already exists
        if (class_exists($className, false)) {
            /** @var class-string<TestCase> */
            return $className;
        }

        $classCode = $this->buildClassCode($className, $testClass);

        // Create the class dynamically
        eval($classCode);

        /** @var class-string<TestCase> */
        return $className;
    }

    /**
     * Generate a unique class name for the dynamic TestCase.
     */
    private function generateClassName(string $originalClassName): string
    {
        $safeName = str_replace('\\', '_', $originalClassName);
        return 'InlineTest_' . $safeName . '_' . substr(md5($originalClassName), 0, 8);
    }

    /**
     * Build the complete PHP class code.
     */
    private function buildClassCode(string $className, InlineTestClass $testClass): string
    {
        $originalClass = $testClass->getReflection();
        $originalClassName = $originalClass?->getName() ?? '';
        $isFunctionBased = $testClass->isFunctionBased();

        // For function-based tests, extract use statements and prepend them
        $useStatements = '';
        if ($isFunctionBased && !empty($testClass->getTestMethods())) {
            $firstFunction = $testClass->getTestMethods()[0];
            if ($firstFunction instanceof \ReflectionFunction) {
                $uses = $this->extractUseStatements($firstFunction);
                if (!empty($uses)) {
                    $useStatements = implode("\n", $uses) . "\n\n";
                }
            }
        }

        // Find default factory if any
        $defaultFactory = $this->findDefaultFactory($testClass);

        // Don't include <?php for eval() - it expects pure PHP code
        $code = $useStatements;
        $code .= "class {$className} extends \\PHPUnit\\Framework\\TestCase\n{\n";

        // Add static state property and accessors (for #[State] support)
        $code .= "    private static mixed \$__state = null;\n\n";
        $code .= "    public static function getState(): mixed\n";
        $code .= "    {\n";
        $code .= "        return self::\$__state;\n";
        $code .= "    }\n\n";
        $code .= "    public static function setState(mixed \$state): void\n";
        $code .= "    {\n";
        $code .= "        self::\$__state = \$state;\n";
        $code .= "    }\n\n";

        // Add property to hold the application class instance (only for class-based tests)
        if (!$isFunctionBased) {
            $code .= "    private object \$instance;\n";
            $code .= "    private ?string \$currentFactory = null;\n\n";
        }

        // Add setUp method to create instance and run Before methods
        $code .= $this->generateSetUpMethod($testClass, $defaultFactory);

        // Add tearDown method to run After methods
        $code .= $this->generateTearDownMethod($testClass);

        // Add setUpBeforeClass for BeforeClass methods and state initialization
        if (!empty($testClass->getBeforeClassMethods()) || $testClass->getStateInitializer() !== null) {
            $code .= $this->generateSetUpBeforeClassMethod($testClass);
        }

        // Add tearDownAfterClass for AfterClass methods
        if (!empty($testClass->getAfterClassMethods())) {
            $code .= $this->generateTearDownAfterClassMethod($testClass);
        }

        // Add all test methods
        foreach ($testClass->getTestMethods() as $testMethod) {
            if ($testMethod instanceof \ReflectionFunction) {
                $code .= $this->generateTestMethodFromFunction($testMethod);
            } else {
                $code .= $this->generateTestMethod($testMethod, $originalClassName, $testClass);
            }
        }

        // Add data provider methods
        $dataProviderMethods = $this->collectDataProviderMethods($testClass);
        foreach ($dataProviderMethods as $providerMethod) {
            $code .= $this->generateDataProviderMethod($providerMethod, $testClass);
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Find the default factory method for a class.
     */
    private function findDefaultFactory(InlineTestClass $testClass): ?string
    {
        $reflection = $testClass->getReflection();
        if ($reflection === null) {
            return null;
        }

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(DefaultFactory::class);
            if (!empty($attributes)) {
                return $method->getName();
            }
        }

        return null;
    }

    /**
     * Find the factory specified for a test method.
     */
    private function findTestFactory(ReflectionMethod $method): ?string
    {
        $attributes = $method->getAttributes(Factory::class);
        if (empty($attributes)) {
            return null;
        }

        $factory = $attributes[0]->newInstance();
        return $factory->methodName;
    }

    /**
     * Collect all data provider methods referenced in test methods.
     *
     * @return array<\ReflectionMethod|\ReflectionFunction>
     */
    private function collectDataProviderMethods(InlineTestClass $testClass): array
    {
        $providers = [];
        $originalClass = $testClass->getReflection();
        $isFunctionBased = $testClass->isFunctionBased();

        foreach ($testClass->getTestMethods() as $testMethod) {
            $attributes = $testMethod->getAttributes(\PHPUnit\Framework\Attributes\DataProvider::class);
            foreach ($attributes as $attribute) {
                $arguments = $attribute->getArguments();
                if (empty($arguments)) {
                    continue;
                }
                if (!is_string($arguments[0])) {
                    continue;
                }
                $providerName = $arguments[0];

                if ($isFunctionBased) {
                    // For function-based tests, look for a provider function in the same namespace
                    $namespace = $testClass->getNamespace();
                    $fullFunctionName = $namespace !== null ? $namespace . '\\' . $providerName : $providerName;

                    if (function_exists($fullFunctionName) && !isset($providers[$providerName])) {
                        $providers[$providerName] = new \ReflectionFunction($fullFunctionName);
                    }
                } else {
                    // For class-based tests, look for a method on the class
                    if ($originalClass !== null && $originalClass->hasMethod($providerName) && !isset($providers[$providerName])) {
                        $providers[$providerName] = $originalClass->getMethod($providerName);
                    }
                }
            }
        }

        return $providers;
    }

    /**
     * Generate a data provider method that calls the original class's provider or function.
     *
     * @param \ReflectionMethod|\ReflectionFunction $providerMethod
     */
    private function generateDataProviderMethod($providerMethod, InlineTestClass $testClass): string
    {
        $methodName = $providerMethod instanceof \ReflectionFunction
            ? $providerMethod->getShortName()
            : $providerMethod->getName();

        $code = "    public static function {$methodName}(): \\Generator|array\n";
        $code .= "    {\n";

        if ($providerMethod instanceof \ReflectionFunction) {
            // Function-based data provider - call the function directly
            $fullFunctionName = $providerMethod->getName();
            $code .= "        return \\{$fullFunctionName}();\n";
        } else {
            // Class-based data provider
            $reflection = $testClass->getReflection();
            if ($reflection === null) {
                throw new \RuntimeException('Class reflection is null for class-based data provider');
            }
            $originalClassName = $reflection->getName();
            $isStatic = $providerMethod->isStatic();

            if ($isStatic) {
                // Static data provider - call directly on the original class
                $code .= "        \$method = new \\ReflectionMethod('\\{$originalClassName}', '{$methodName}');\n";
                $code .= "        \$method->setAccessible(true);\n";
                $code .= "        return \$method->invoke(null);\n";
            } else {
                // Instance data provider - create temp instance and call
                $code .= "        \$instance = new \\{$originalClassName}();\n";
                $code .= "        \$method = new \\ReflectionMethod(\$instance, '{$methodName}');\n";
                $code .= "        \$method->setAccessible(true);\n";
                $code .= "        return \$method->invoke(\$instance);\n";
            }
        }

        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Generate the setUp method that creates the instance and runs Before methods.
     */
    private function generateSetUpMethod(InlineTestClass $testClass, ?string $defaultFactory = null): string
    {
        $originalClass = $testClass->getReflection();

        $code = "    protected function setUp(): void\n";
        $code .= "    {\n";
        $code .= "        parent::setUp();\n";

        // Only create instance for class-based tests
        if ($originalClass !== null) {
            $originalClassName = $originalClass->getName();

            if ($defaultFactory !== null) {
                // Use default factory, but allow per-test override via currentFactory
                $code .= "        \$factoryName = \$this->currentFactory ?? '{$defaultFactory}';\n";
                $code .= "        \$factory = new \\ReflectionMethod('\\{$originalClassName}', \$factoryName);\n";
                $code .= "        \$factory->setAccessible(true);\n";
                $code .= "        \$this->instance = \$factory->invoke(null);\n";
            } else {
                // Check if the class has a constructor with required parameters
                $constructor = $originalClass->getConstructor();
                $hasRequiredParams = false;
                if ($constructor !== null) {
                    foreach ($constructor->getParameters() as $param) {
                        if (!$param->isOptional()) {
                            $hasRequiredParams = true;
                            break;
                        }
                    }
                }

                if ($hasRequiredParams) {
                    // Class requires constructor args but has no factory - check for currentFactory
                    $code .= "        if (\$this->currentFactory !== null) {\n";
                    $code .= "            \$factory = new \\ReflectionMethod('\\{$originalClassName}', \$this->currentFactory);\n";
                    $code .= "            \$factory->setAccessible(true);\n";
                    $code .= "            \$this->instance = \$factory->invoke(null);\n";
                    $code .= "        } else {\n";
                    $code .= "            throw new \\RuntimeException('Class {$originalClassName} requires constructor arguments. Use #[Factory] or #[DefaultFactory] attribute.');\n";
                    $code .= "        }\n";
                } else {
                    // No required constructor params - can instantiate directly, but allow factory override
                    $code .= "        if (\$this->currentFactory !== null) {\n";
                    $code .= "            \$factory = new \\ReflectionMethod('\\{$originalClassName}', \$this->currentFactory);\n";
                    $code .= "            \$factory->setAccessible(true);\n";
                    $code .= "            \$this->instance = \$factory->invoke(null);\n";
                    $code .= "        } else {\n";
                    $code .= "            \$this->instance = new \\{$originalClassName}();\n";
                    $code .= "        }\n";
                }
            }
        }

        // Add Before method/function calls
        foreach ($testClass->getBeforeMethods() as $method) {
            if ($method instanceof \ReflectionFunction) {
                $functionName = $method->getName();
                $code .= "        \\{$functionName}();\n";
            } else {
                $methodName = $method->getName();
                $code .= "        \$method = new \\ReflectionMethod(\$this->instance, '{$methodName}');\n";
                $code .= "        \$method->setAccessible(true);\n";
                $code .= "        \$method->invoke(\$this->instance);\n";
            }
        }

        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Generate the tearDown method that runs After methods.
     */
    private function generateTearDownMethod(InlineTestClass $testClass): string
    {
        if (empty($testClass->getAfterMethods())) {
            return '';
        }

        $code = "    protected function tearDown(): void\n";
        $code .= "    {\n";

        // Add After method/function calls
        foreach ($testClass->getAfterMethods() as $method) {
            if ($method instanceof \ReflectionFunction) {
                $functionName = $method->getName();
                $code .= "        \\{$functionName}();\n";
            } else {
                $methodName = $method->getName();
                $code .= "        \$method = new \\ReflectionMethod(\$this->instance, '{$methodName}');\n";
                $code .= "        \$method->setAccessible(true);\n";
                $code .= "        \$method->invoke(\$this->instance);\n";
            }
        }

        $code .= "        parent::tearDown();\n";
        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Generate the setUpBeforeClass method for BeforeClass methods and state initialization.
     */
    private function generateSetUpBeforeClassMethod(InlineTestClass $testClass): string
    {
        $originalClass = $testClass->getReflection();
        $stateInitializer = $testClass->getStateInitializer();

        $code = "    public static function setUpBeforeClass(): void\n";
        $code .= "    {\n";
        $code .= "        parent::setUpBeforeClass();\n";

        // Initialize state if there's a state initializer
        if ($stateInitializer !== null) {
            if ($stateInitializer instanceof \ReflectionFunction) {
                $functionName = $stateInitializer->getName();
                $code .= "        self::\$__state = \\{$functionName}();\n";
            } else {
                if ($originalClass === null) {
                    throw new \RuntimeException('Class reflection is null for class-based State method');
                }
                $originalClassName = $originalClass->getName();
                $methodName = $stateInitializer->getName();
                $code .= "        \$method = new \\ReflectionMethod('\\{$originalClassName}', '{$methodName}');\n";
                $code .= "        \$method->setAccessible(true);\n";
                $code .= "        self::\$__state = \$method->invoke(null);\n";
            }
        }

        foreach ($testClass->getBeforeClassMethods() as $method) {
            if ($method instanceof \ReflectionFunction) {
                $functionName = $method->getName();
                $code .= "        \\{$functionName}();\n";
            } else {
                if ($originalClass === null) {
                    throw new \RuntimeException('Class reflection is null for class-based BeforeClass method');
                }
                $originalClassName = $originalClass->getName();
                $methodName = $method->getName();
                $code .= "        \$method = new \\ReflectionMethod('\\{$originalClassName}', '{$methodName}');\n";
                $code .= "        \$method->setAccessible(true);\n";
                $code .= "        \$method->invoke(null);\n";
            }
        }

        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Generate the tearDownAfterClass method for AfterClass methods.
     */
    private function generateTearDownAfterClassMethod(InlineTestClass $testClass): string
    {
        $originalClass = $testClass->getReflection();

        $code = "    public static function tearDownAfterClass(): void\n";
        $code .= "    {\n";

        foreach ($testClass->getAfterClassMethods() as $method) {
            if ($method instanceof \ReflectionFunction) {
                $functionName = $method->getName();
                $code .= "        \\{$functionName}();\n";
            } else {
                if ($originalClass === null) {
                    throw new \RuntimeException('Class reflection is null for class-based AfterClass method');
                }
                $originalClassName = $originalClass->getName();
                $methodName = $method->getName();
                $code .= "        \$method = new \\ReflectionMethod('\\{$originalClassName}', '{$methodName}');\n";
                $code .= "        \$method->setAccessible(true);\n";
                $code .= "        \$method->invoke(null);\n";
            }
        }

        $code .= "        parent::tearDownAfterClass();\n";
        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Generate a test method by extracting and adapting the original method body.
     */
    private function generateTestMethod(ReflectionMethod $method, string $originalClassName, InlineTestClass $testClass): string
    {
        $methodName = $method->getName();
        $methodBody = $this->extractMethodBody($method);

        // Check if this test specifies a factory
        $testFactory = $this->findTestFactory($method);

        // Get attributes to preserve them (excluding our Factory attribute which is handled separately)
        $attributes = $this->generateAttributes($method, [Factory::class]);

        $code = $attributes;
        $code .= "    public function {$methodName}(";

        // Add parameters if any (for data providers)
        $params = [];
        $paramNames = [];
        foreach ($method->getParameters() as $param) {
            $paramStr = '';
            if ($param->hasType()) {
                $type = $param->getType();
                if ($type !== null) {
                    $paramStr .= $type->__toString() . ' ';
                }
            }
            $paramStr .= '$' . $param->getName();
            $paramNames[] = '$' . $param->getName();
            if ($param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                $paramStr .= ' = ' . var_export($default, true);
            }
            $params[] = $paramStr;
        }
        $code .= implode(', ', $params);

        $code .= "): void\n";
        $code .= "    {\n";

        // If test specifies a factory, set it before setUp runs
        if ($testFactory !== null) {
            $code .= "        // Set factory for this test\n";
            $code .= "        \$this->currentFactory = '{$testFactory}';\n";
            $code .= "        \$this->setUp();\n";
            $code .= "        \n";
        }

        // Adapt the method body to work in the TestCase context
        $adaptedBody = $this->adaptMethodBody($methodBody, $originalClassName, $paramNames);
        $code .= $adaptedBody;

        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Extract the method body from the source file.
     */
    private function extractMethodBody(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            throw new \RuntimeException('Could not get method source information');
        }

        $source = file($filename);
        if ($source === false) {
            throw new \RuntimeException('Could not read source file');
        }

        // Extract method body
        $methodCode = implode('', array_slice($source, $startLine, $endLine - $startLine));

        // Remove the method signature and opening/closing braces
        $methodCode = preg_replace('/^.*?\{/s', '', $methodCode);
        if ($methodCode === null) {
            throw new \RuntimeException('Failed to extract method body');
        }
        $methodCode = preg_replace('/\}\s*$/s', '', $methodCode);

        if ($methodCode === null) {
            throw new \RuntimeException('Could not extract method body');
        }

        return $methodCode;
    }

    /**
     * Generate a test method from a standalone function.
     */
    private function generateTestMethodFromFunction(\ReflectionFunction $function): string
    {
        // Get short name without namespace
        $functionName = $function->getShortName();
        $functionBody = $this->extractFunctionBody($function);

        // Get attributes to preserve them
        $attributes = $this->generateAttributesFromFunction($function);

        $code = $attributes;
        $code .= "    public function {$functionName}(";

        // Add parameters if any (for data providers)
        $params = [];
        foreach ($function->getParameters() as $param) {
            $paramStr = '';
            if ($param->hasType()) {
                $type = $param->getType();
                if ($type !== null) {
                    $paramStr .= $type->__toString() . ' ';
                }
            }
            $paramStr .= '$' . $param->getName();
            if ($param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                $paramStr .= ' = ' . var_export($default, true);
            }
            $params[] = $paramStr;
        }
        $code .= implode(', ', $params);

        $code .= "): void\n";
        $code .= "    {\n";

        // Build list of parameter names for closure use
        $paramNames = [];
        foreach ($function->getParameters() as $param) {
            $paramNames[] = '$' . $param->getName();
        }

        // For functions, set up the test() helper and call the function directly
        $fullFunctionName = $function->getName();
        $useClause = !empty($paramNames) ? implode(', ', $paramNames) : '';

        $adapted = "        // Set up test() helper for PHPUnit assertions\n";
        $adapted .= "        global \$__inlineTestCase;\n";
        $adapted .= "        \$__inlineTestCase = \$this;\n";
        $adapted .= "        \n";
        $adapted .= "        // Call the test function directly\n";
        $adapted .= "        \\{$fullFunctionName}({$useClause});\n";

        $code .= $adapted;
        $code .= "    }\n\n";

        return $code;
    }

    /**
     * Extract the function body from the source file.
     */
    private function extractFunctionBody(\ReflectionFunction $function): string
    {
        $filename = $function->getFileName();
        $startLine = $function->getStartLine();
        $endLine = $function->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            throw new \RuntimeException('Could not get function source information');
        }

        $source = file($filename);
        if ($source === false) {
            throw new \RuntimeException('Could not read source file');
        }

        // Extract function body
        $functionCode = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

        // Remove the function signature and opening/closing braces
        $functionCode = preg_replace('/^.*?\{/s', '', $functionCode);
        if ($functionCode === null) {
            throw new \RuntimeException('Failed to extract function body');
        }
        $functionCode = preg_replace('/\}\s*$/s', '', $functionCode);

        if ($functionCode === null) {
            throw new \RuntimeException('Could not extract function body');
        }

        return $functionCode;
    }

    /**
     * Extract use statements from the file containing the function.
     *
     * @return array<string>
     */
    private function extractUseStatements(\ReflectionFunction $function): array
    {
        $filename = $function->getFileName();
        if ($filename === false) {
            return [];
        }

        $contents = @file_get_contents($filename);
        if ($contents === false) {
            return [];
        }

        $useStatements = [];
        $tokens = token_get_all($contents);
        $namespace = $function->getNamespaceName();
        $currentNamespace = '';
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            // Track namespace changes
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $i++;
                $namespaceParts = [];

                while ($i < $count) {
                    $token = $tokens[$i];

                    if (is_array($token)) {
                        if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                            $namespaceParts[] = $token[1];
                        } elseif ($token[0] === T_NS_SEPARATOR) {
                            // Continue
                        } elseif ($token[0] === T_WHITESPACE) {
                            // Skip
                        } else {
                            break;
                        }
                    } elseif ($token === ';' || $token === '{') {
                        break;
                    }

                    $i++;
                }

                $currentNamespace = implode('\\', $namespaceParts);
                $i++;
                continue;
            }

            // Look for use statements in the same namespace as the function
            if (is_array($token) && $token[0] === T_USE && $currentNamespace === $namespace) {
                $useStatement = 'use ';
                $i++;

                // Check if it's a function or const use
                while ($i < $count) {
                    $token = $tokens[$i];

                    if (is_array($token) && $token[0] === T_FUNCTION) {
                        $useStatement .= 'function ';
                        $i++;
                        break;
                    } elseif (is_array($token) && $token[0] === T_CONST) {
                        $useStatement .= 'const ';
                        $i++;
                        break;
                    } elseif (is_array($token) && $token[0] === T_WHITESPACE) {
                        $i++;
                    } else {
                        break;
                    }
                }

                // Collect the rest of the use statement
                while ($i < $count) {
                    $token = $tokens[$i];

                    if ($token === ';') {
                        $useStatement .= ';';
                        $useStatements[] = $useStatement;
                        break;
                    } elseif (is_array($token)) {
                        $useStatement .= $token[1];
                    } else {
                        $useStatement .= $token;
                    }

                    $i++;
                }
            }

            $i++;
        }

        return $useStatements;
    }

    /**
     * Generate attributes for a function.
     */
    private function generateAttributesFromFunction(\ReflectionFunction $function): string
    {
        $code = '';

        // Get all attributes
        $attributes = $function->getAttributes();

        foreach ($attributes as $attribute) {
            $name = $attribute->getName();
            $args = $attribute->getArguments();

            $code .= "    #[\\{$name}";

            if (!empty($args)) {
                $argStrings = [];
                foreach ($args as $key => $value) {
                    if (is_string($key)) {
                        $argStrings[] = $key . ': ' . var_export($value, true);
                    } else {
                        $argStrings[] = var_export($value, true);
                    }
                }
                $code .= '(' . implode(', ', $argStrings) . ')';
            }

            $code .= "]\n";
        }

        return $code;
    }

    /**
     * Adapt the method body to work in the TestCase class context.
     * Sets up the test() helper function to provide access to PHPUnit assertions.
     *
     * We inline the test code directly in the TestCase method to ensure PHPUnit's
     * protected methods (like createMock) are accessible via test()->createMock().
     *
     * @param array<string> $paramNames Parameter variable names (e.g., ['$input', '$expected'])
     */
    private function adaptMethodBody(string $methodBody, string $originalClassName, array $paramNames = []): string
    {
        // Strategy: Set up the global test() helper and inline the test code.
        // We create the instance and make it available, then execute the test code
        // in the TestCase scope where protected methods are accessible.

        $adapted = "        // Set up test() helper for PHPUnit assertions\n";
        $adapted .= "        global \$__inlineTestCase;\n";
        $adapted .= "        \$__inlineTestCase = \$this;\n";
        $adapted .= "        \n";
        $adapted .= "        // Make instance available as \$this would be in the original test\n";
        $adapted .= "        \$__self = \$this->instance;\n";
        $adapted .= "        \n";

        // Build use clause for data provider parameters
        $useClause = !empty($paramNames) ? ' use (' . implode(', ', $paramNames) . ')' : '';

        $adapted .= "        // Create a closure bound to the instance so \$this->method() works\n";
        $adapted .= "        \$__testFn = \\Closure::bind(function(){$useClause} {\n";

        // Indent the original method body
        $indentedBody = preg_replace('/^/m', '            ', $methodBody);
        if ($indentedBody === null) {
            $indentedBody = '            ' . str_replace("\n", "\n            ", $methodBody);
        }
        $adapted .= $indentedBody;

        $adapted .= "\n        }, \$__self, \\{$originalClassName}::class);\n";
        $adapted .= "        \n";
        $adapted .= "        \$__testFn();\n";

        return $adapted;
    }

    /**
     * Generate attribute declarations for a method.
     *
     * @param array<class-string> $excludeAttributes Attribute classes to exclude from output
     */
    private function generateAttributes(ReflectionMethod $method, array $excludeAttributes = []): string
    {
        $code = '';

        foreach ($method->getAttributes() as $attribute) {
            $name = $attribute->getName();

            // Skip excluded attributes
            if (in_array($name, $excludeAttributes, true)) {
                continue;
            }

            $args = $attribute->getArguments();

            $code .= "    #[\\{$name}";
            if (!empty($args)) {
                $argStrings = [];
                foreach ($args as $key => $value) {
                    if (is_string($key)) {
                        $argStrings[] = $key . ': ' . var_export($value, true);
                    } else {
                        $argStrings[] = var_export($value, true);
                    }
                }
                $code .= '(' . implode(', ', $argStrings) . ')';
            }
            $code .= "]\n";
        }

        return $code;
    }
}
