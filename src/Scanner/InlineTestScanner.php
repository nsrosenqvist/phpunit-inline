<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Scanner;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use SplFileInfo;

final class InlineTestScanner
{
    /**
     * @param array<string> $scanDirectories
     */
    public function __construct(
        private readonly array $scanDirectories
    ) {
    }

    /**
     * Scans configured directories for classes containing methods with #[Test] attribute.
     *
     * @return array<InlineTestClass>
     */
    public function scan(): array
    {
        $testClasses = [];

        foreach ($this->scanDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $testClasses = array_merge(
                $testClasses,
                $this->scanDirectory($directory)
            );
        }

        return $testClasses;
    }

    /**
     * @return array<InlineTestClass>
     */
    private function scanDirectory(string $directory): array
    {
        $testClasses = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Scan for class-based tests
            $classNames = $this->extractClassNames($file->getPathname());

            foreach ($classNames as $className) {
                try {
                    if (!class_exists($className)) {
                        continue;
                    }

                    $reflection = new ReflectionClass($className);
                    $testMethods = $this->findTestMethods($reflection);

                    if (!empty($testMethods)) {
                        $testClasses[] = new InlineTestClass(
                            $reflection,
                            $testMethods,
                            $this->findLifecycleMethods($reflection, Before::class),
                            $this->findLifecycleMethods($reflection, After::class),
                            $this->findLifecycleMethods($reflection, BeforeClass::class),
                            $this->findLifecycleMethods($reflection, AfterClass::class)
                        );
                    }
                } catch (ReflectionException) {
                    // Skip files that can't be reflected
                    continue;
                }
            }

            // Scan for function-based tests
            $functionTests = $this->extractTestFunctions($file->getPathname());
            $testClasses = array_merge($testClasses, $functionTests);
        }

        return $testClasses;
    }

    /**
     * Extracts all fully qualified class names from a PHP file.
     * Supports multiple namespace declarations in a single file.
     *
     * @return array<string>
     */
    private function extractClassNames(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return [];
        }

        $classNames = [];
        $tokens = token_get_all($contents);
        $currentNamespace = '';
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            // Track namespace changes
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $i++;
                $namespaceParts = [];

                // Collect all parts of the namespace
                while ($i < $count) {
                    $token = $tokens[$i];

                    if (is_array($token)) {
                        if ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED) {
                            $namespaceParts[] = $token[1];
                        } elseif ($token[0] === T_STRING) {
                            $namespaceParts[] = $token[1];
                        } elseif ($token[0] === T_NS_SEPARATOR) {
                            // Continue collecting
                        } elseif ($token[0] === T_WHITESPACE) {
                            // Skip whitespace
                        } else {
                            break;
                        }
                    } elseif ($token === ';' || $token === '{') {
                        break;
                    }

                    $i++;
                }

                $currentNamespace = implode('\\', $namespaceParts);
                continue;
            }

            // Look for class/interface/trait/enum declarations
            if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                // Skip "final" and "abstract" keywords
                $j = $i - 1;
                while ($j >= 0 && is_array($tokens[$j])) {
                    if (in_array($tokens[$j][0], [T_FINAL, T_ABSTRACT, T_WHITESPACE], true)) {
                        $j--;
                    } else {
                        break;
                    }
                }

                // Find the class name
                $i++;
                while ($i < $count) {
                    $token = $tokens[$i];

                    if (is_array($token) && $token[0] === T_STRING) {
                        $className = $token[1];
                        $fqcn = $currentNamespace !== '' ? $currentNamespace . '\\' . $className : $className;
                        $classNames[] = $fqcn;
                        break;
                    }

                    $i++;
                }
            }

            $i++;
        }

        return $classNames;
    }

    /**
     * Finds all methods in a class that have the #[Test] attribute.
     *
     * Only returns test methods for classes that don't extend TestCase.
     * Classes extending TestCase should be discovered by PHPUnit's normal discovery.
     *
     * @param ReflectionClass<object> $reflection
     * @return array<ReflectionMethod>
     */
    private function findTestMethods(ReflectionClass $reflection): array
    {
        // Skip classes that extend TestCase - let PHPUnit handle them normally
        if ($reflection->isSubclassOf(TestCase::class)) {
            return [];
        }

        $testMethods = [];

        foreach ($reflection->getMethods() as $method) {
            $testAttributes = $method->getAttributes(
                Test::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            if (!empty($testAttributes)) {
                $testMethods[] = $method;
            }
        }

        return $testMethods;
    }

    /**
     * Find the data provider method name for a test method.
     *
     * @return string|null The provider method name, or null if no provider
     */
    public function findDataProvider(ReflectionMethod $method): ?string
    {
        $attributes = $method->getAttributes(
            DataProvider::class,
            ReflectionAttribute::IS_INSTANCEOF
        );

        if (empty($attributes)) {
            return null;
        }

        $attribute = $attributes[0]->newInstance();

        return $attribute->methodName();
    }

    /**
     * Finds all methods in a class that have a specific lifecycle attribute.
     *
     * @param ReflectionClass<object> $reflection
     * @param class-string $attributeClass
     * @return array<ReflectionMethod>
     */
    private function findLifecycleMethods(ReflectionClass $reflection, string $attributeClass): array
    {
        $lifecycleMethods = [];

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(
                $attributeClass,
                ReflectionAttribute::IS_INSTANCEOF
            );

            if (!empty($attributes)) {
                $lifecycleMethods[] = $method;
            }
        }

        return $lifecycleMethods;
    }

    /**
     * Extract test functions from a PHP file and group them by namespace.
     *
     * @return array<InlineTestClass>
     */
    private function extractTestFunctions(string $filePath): array
    {
        // Get functions before including file
        $functionsBefore = get_defined_functions()['user'];

        // Include the file to make functions available (use include to allow re-inclusion)
        // Note: We track what was loaded before to handle files included multiple times
        include_once $filePath;

        // Get functions after including file
        $functionsAfter = get_defined_functions()['user'];

        // Find newly defined functions
        $newFunctions = array_diff($functionsAfter, $functionsBefore);

        // If no new functions (file was already included), we need to parse the file
        // to find which functions it defines and check them all
        if (empty($newFunctions)) {
            $newFunctions = $this->extractFunctionNamesFromFile($filePath);
        }

        // Group functions by namespace
        $namespaceGroups = [];

        foreach ($newFunctions as $functionName) {
            try {
                if (!function_exists($functionName)) {
                    continue;
                }

                $reflection = new ReflectionFunction($functionName);

                // Get namespace from function (namespace is part of the function name)
                $namespace = $reflection->getNamespaceName();

                // Check if function has Test attribute
                $testAttributes = $reflection->getAttributes(
                    Test::class,
                    ReflectionAttribute::IS_INSTANCEOF
                );

                if (!empty($testAttributes)) {
                    if (!isset($namespaceGroups[$namespace])) {
                        $namespaceGroups[$namespace] = [
                            'tests' => [],
                            'before' => [],
                            'after' => [],
                            'beforeClass' => [],
                            'afterClass' => [],
                        ];
                    }
                    $namespaceGroups[$namespace]['tests'][] = $reflection;
                }

                // Check for lifecycle attributes
                if (!empty($reflection->getAttributes(Before::class, ReflectionAttribute::IS_INSTANCEOF))) {
                    if (!isset($namespaceGroups[$namespace])) {
                        $namespaceGroups[$namespace] = [
                            'tests' => [],
                            'before' => [],
                            'after' => [],
                            'beforeClass' => [],
                            'afterClass' => [],
                        ];
                    }
                    $namespaceGroups[$namespace]['before'][] = $reflection;
                }

                if (!empty($reflection->getAttributes(After::class, ReflectionAttribute::IS_INSTANCEOF))) {
                    if (!isset($namespaceGroups[$namespace])) {
                        $namespaceGroups[$namespace] = [
                            'tests' => [],
                            'before' => [],
                            'after' => [],
                            'beforeClass' => [],
                            'afterClass' => [],
                        ];
                    }
                    $namespaceGroups[$namespace]['after'][] = $reflection;
                }

                if (!empty($reflection->getAttributes(BeforeClass::class, ReflectionAttribute::IS_INSTANCEOF))) {
                    if (!isset($namespaceGroups[$namespace])) {
                        $namespaceGroups[$namespace] = [
                            'tests' => [],
                            'before' => [],
                            'after' => [],
                            'beforeClass' => [],
                            'afterClass' => [],
                        ];
                    }
                    $namespaceGroups[$namespace]['beforeClass'][] = $reflection;
                }

                if (!empty($reflection->getAttributes(AfterClass::class, ReflectionAttribute::IS_INSTANCEOF))) {
                    if (!isset($namespaceGroups[$namespace])) {
                        $namespaceGroups[$namespace] = [
                            'tests' => [],
                            'before' => [],
                            'after' => [],
                            'beforeClass' => [],
                            'afterClass' => [],
                        ];
                    }
                    $namespaceGroups[$namespace]['afterClass'][] = $reflection;
                }

            } catch (\ReflectionException) {
                continue;
            }
        }

        // Convert namespace groups to InlineTestClass instances
        $testClasses = [];
        foreach ($namespaceGroups as $namespace => $functions) {
            if (!empty($functions['tests'])) {
                $testClasses[] = new InlineTestClass(
                    null, // No class reflection for function-based tests
                    $functions['tests'],
                    $functions['before'],
                    $functions['after'],
                    $functions['beforeClass'],
                    $functions['afterClass'],
                    $namespace
                );
            }
        }

        return $testClasses;
    }

    /**
     * Extract function names from a file by parsing tokens.
     * Used when file was already included.
     *
     * @return array<string>
     */
    private function extractFunctionNamesFromFile(string $filePath): array
    {
        $contents = @file_get_contents($filePath);
        if ($contents === false) {
            return [];
        }

        $functions = [];
        $tokens = token_get_all($contents);
        $currentNamespace = '';
        $i = 0;
        $count = count($tokens);
        $braceDepth = 0;
        $inClass = false;

        while ($i < $count) {
            $token = $tokens[$i];

            // Track namespace changes
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $i++;
                $namespaceParts = [];

                while ($i < $count) {
                    $token = $tokens[$i];

                    if (is_array($token)) {
                        if ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED) {
                            $namespaceParts[] = $token[1];
                        } elseif ($token[0] === T_STRING) {
                            $namespaceParts[] = $token[1];
                        } elseif ($token[0] === T_NS_SEPARATOR) {
                            // Continue collecting
                        } elseif ($token[0] === T_WHITESPACE) {
                            // Skip whitespace
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

            // Track class/interface/trait declarations
            if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $inClass = true;
                $i++;
                continue;
            }

            // Track braces to know when we exit a class
            if ($token === '{') {
                $braceDepth++;
            } elseif ($token === '}') {
                $braceDepth--;
                if ($braceDepth === 0) {
                    $inClass = false;
                }
            }

            // Look for function declarations (only at namespace level, not in classes)
            if (is_array($token) && $token[0] === T_FUNCTION && !$inClass) {
                // Find the function name
                $i++;
                while ($i < $count) {
                    $token = $tokens[$i];

                    if (is_array($token) && $token[0] === T_STRING) {
                        $functionName = $token[1];
                        $fullName = $currentNamespace !== '' ? $currentNamespace . '\\' . $functionName : $functionName;
                        $functions[] = $fullName;
                        break;
                    }

                    $i++;
                }
            }

            $i++;
        }

        return $functions;
    }
}
