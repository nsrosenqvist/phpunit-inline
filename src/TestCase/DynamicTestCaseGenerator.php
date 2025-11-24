<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\TestCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestClass;
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
            return $className;
        }

        $classCode = $this->buildClassCode($className, $testClass);

        // Create the class
        eval($classCode);

        if (!class_exists($className, false)) {
            throw new \RuntimeException("Failed to generate test class: {$className}");
        }

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

        // Don't include <?php for eval() - it expects pure PHP code
        $code = $useStatements;
        $code .= "class {$className} extends \\PHPUnit\\Framework\\TestCase\n{\n";

        // Add property to hold the application class instance (only for class-based tests)
        if (!$isFunctionBased) {
            $code .= "    private object \$instance;\n\n";
        }

        // Add setUp method to create instance and run Before methods
        $code .= $this->generateSetUpMethod($testClass);

        // Add tearDown method to run After methods
        $code .= $this->generateTearDownMethod($testClass);

        // Add setUpBeforeClass for BeforeClass methods
        if (!empty($testClass->getBeforeClassMethods())) {
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
                $code .= $this->generateTestMethod($testMethod, $originalClassName);
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
     * Collect all data provider methods referenced in test methods.
     *
     * @return array<ReflectionMethod|ReflectionFunction>
     */
    private function collectDataProviderMethods(InlineTestClass $testClass): array
    {
        $providers = [];
        $originalClass = $testClass->getReflection();
        $isFunctionBased = $testClass->isFunctionBased();

        foreach ($testClass->getTestMethods() as $testMethod) {
            $attributes = $testMethod->getAttributes(\PHPUnit\Framework\Attributes\DataProvider::class);
            foreach ($attributes as $attribute) {
                $providerName = $attribute->getArguments()[0];
                
                if ($isFunctionBased) {
                    // For function-based tests, look for a provider function in the same namespace
                    $namespace = $testClass->getNamespace();
                    $fullFunctionName = $namespace ? $namespace . '\\' . $providerName : $providerName;
                    
                    if (function_exists($fullFunctionName) && !isset($providers[$providerName])) {
                        $providers[$providerName] = new \ReflectionFunction($fullFunctionName);
                    }
                } else {
                    // For class-based tests, look for a method on the class
                    if ($originalClass->hasMethod($providerName) && !isset($providers[$providerName])) {
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
            $originalClassName = $testClass->getReflection()->getName();
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
    private function generateSetUpMethod(InlineTestClass $testClass): string
    {
        $originalClass = $testClass->getReflection();

        $code = "    protected function setUp(): void\n";
        $code .= "    {\n";
        $code .= "        parent::setUp();\n";

        // Only create instance for class-based tests
        if ($originalClass !== null) {
            $originalClassName = $originalClass->getName();
            $code .= "        \$this->instance = new \\{$originalClassName}();\n";
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
     * Generate the setUpBeforeClass method for BeforeClass methods.
     */
    private function generateSetUpBeforeClassMethod(InlineTestClass $testClass): string
    {
        $originalClass = $testClass->getReflection();

        $code = "    public static function setUpBeforeClass(): void\n";
        $code .= "    {\n";
        $code .= "        parent::setUpBeforeClass();\n";

        foreach ($testClass->getBeforeClassMethods() as $method) {
            if ($method instanceof \ReflectionFunction) {
                $functionName = $method->getName();
                $code .= "        \\{$functionName}();\n";
            } else {
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
    private function generateTestMethod(ReflectionMethod $method, string $originalClassName): string
    {
        $methodName = $method->getName();
        $methodBody = $this->extractMethodBody($method);

        // Get attributes to preserve them
        $attributes = $this->generateAttributes($method);

        $code = $attributes;
        $code .= "    public function {$methodName}(";

        // Add parameters if any (for data providers)
        $params = [];
        foreach ($method->getParameters() as $param) {
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

        // Adapt the method body to work in the TestCase context
        $adaptedBody = $this->adaptMethodBody($methodBody, $originalClassName);
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

        // For functions, just bind to TestCase context
        $adapted = "        // Execute test function with TestCase context\n";
        $adapted .= "        \$__testCase = \$this;\n";
        $adapted .= "        \n";
        $adapted .= "        // Execute the function body with \$this bound to TestCase\n";
        if (!empty($paramNames)) {
            $useClause = ' use (' . implode(', ', $paramNames) . ')';
        } else {
            $useClause = '';
        }
        $adapted .= "        \$__executor = function(){$useClause} {\n";
        $adapted .= $functionBody;
        $adapted .= "        };\n";
        $adapted .= "        \n";
        $adapted .= "        \$__bound = \\Closure::bind(\$__executor, \$__testCase, get_class(\$__testCase));\n";
        $adapted .= "        \$__bound();\n";

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
     * Redirects $this-> calls to work with the instance through a proxy.
     */
    private function adaptMethodBody(string $methodBody, string $originalClassName): string
    {
        // Strategy: Use a closure bound to a proxy object that handles both instance and TestCase access
        // We'll extract the method body and execute it in a context where $this means the proxy

        $adapted = "        // Execute test method with proxy context\n";
        $adapted .= "        \$__instance = \$this->instance;\n";
        $adapted .= "        \$__testCase = \$this;\n";
        $adapted .= "        \n";
        $adapted .= "        // Create a proxy that delegates to both instance and TestCase\n";
        $adapted .= "        \$__proxy = new class(\$__instance, \$__testCase) {\n";
        $adapted .= "            public function __construct(\n";
        $adapted .= "                private object \$instance,\n";
        $adapted .= "                private \\PHPUnit\\Framework\\TestCase \$testCase\n";
        $adapted .= "            ) {}\n";
        $adapted .= "            \n";
        $adapted .= "            public function __call(string \$method, array \$args): mixed {\n";
        $adapted .= "                if (method_exists(\$this->instance, \$method)) {\n";
        $adapted .= "                    \$reflection = new \\ReflectionMethod(\$this->instance, \$method);\n";
        $adapted .= "                    \$reflection->setAccessible(true);\n";
        $adapted .= "                    return \$reflection->invoke(\$this->instance, ...\$args);\n";
        $adapted .= "                }\n";
        $adapted .= "                if (method_exists(\$this->testCase, \$method)) {\n";
        $adapted .= "                    return \$this->testCase->\$method(...\$args);\n";
        $adapted .= "                }\n";
        $adapted .= "                throw new \\BadMethodCallException(\"Method \$method not found\");\n";
        $adapted .= "            }\n";
        $adapted .= "            \n";
        $adapted .= "            public function __get(string \$name): mixed {\n";
        $adapted .= "                \$reflection = new \\ReflectionClass(\$this->instance);\n";
        $adapted .= "                if (\$reflection->hasProperty(\$name)) {\n";
        $adapted .= "                    \$property = \$reflection->getProperty(\$name);\n";
        $adapted .= "                    \$property->setAccessible(true);\n";
        $adapted .= "                    return \$property->getValue(\$this->instance);\n";
        $adapted .= "                }\n";
        $adapted .= "                throw new \\RuntimeException(\"Property \$name not found\");\n";
        $adapted .= "            }\n";
        $adapted .= "            \n";
        $adapted .= "            public function __set(string \$name, mixed \$value): void {\n";
        $adapted .= "                \$reflection = new \\ReflectionClass(\$this->instance);\n";
        $adapted .= "                if (\$reflection->hasProperty(\$name)) {\n";
        $adapted .= "                    \$property = \$reflection->getProperty(\$name);\n";
        $adapted .= "                    \$property->setAccessible(true);\n";
        $adapted .= "                    \$property->setValue(\$this->instance, \$value);\n";
        $adapted .= "                    return;\n";
        $adapted .= "                }\n";
        $adapted .= "                \$this->instance->\$name = \$value;\n";
        $adapted .= "            }\n";
        $adapted .= "        };\n";
        $adapted .= "        \n";
        $adapted .= "        // Execute the method body with \$this bound to the proxy\n";
        $adapted .= "        \$__executor = function() {\n";
        $adapted .= $methodBody;
        $adapted .= "        };\n";
        $adapted .= "        \n";
        $adapted .= "        \$__bound = \\Closure::bind(\$__executor, \$__proxy, get_class(\$__proxy));\n";
        $adapted .= "        \$__bound();\n";

        return $adapted;
    }

    /**
     * Generate attribute declarations for a method.
     */
    private function generateAttributes(ReflectionMethod $method): string
    {
        $code = '';

        foreach ($method->getAttributes() as $attribute) {
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
}
