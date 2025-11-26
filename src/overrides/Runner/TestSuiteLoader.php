<?php

declare(strict_types=1);

/*
 * This file overrides PHPUnit's TestSuiteLoader to support inline tests.
 *
 * Based on PHPUnit's original TestSuiteLoader with modifications to:
 * 1. Include source files (which registers inline tests via the scanner)
 * 2. Generate dynamic TestCase classes for files with inline tests
 * 3. Return the generated class to PHPUnit for test execution
 */

namespace PHPUnit\Runner;

use NSRosenqvist\PHPUnitInline\TestSuite as InlineTestSuite;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestSuiteLoader
{
    /**
     * Classes that have already been loaded.
     *
     * @var array<class-string>
     */
    private static array $loadedClasses = [];

    /**
     * Classes grouped by the file they were defined in.
     *
     * @var array<string, array<class-string>>
     */
    private static array $loadedClassesByFilename = [];

    /**
     * Classes that were declared before our loading process.
     *
     * @var array<class-string>
     */
    private static array $declaredClasses = [];

    public function __construct()
    {
        if (empty(self::$declaredClasses)) {
            self::$declaredClasses = get_declared_classes();
        }
    }

    /**
     * Load a test class from a file.
     *
     * This is the main entry point called by PHPUnit's TestSuiteBuilder.
     *
     * @return ReflectionClass<object>
     *
     * @throws \Exception
     */
    public function load(string $suiteClassFile): ReflectionClass
    {
        $suiteClassName = $this->classNameFromFileName($suiteClassFile);

        // Include the file - this will:
        // 1. Load any classes/functions defined in it
        // 2. For function-based tests, the scanner has already registered them
        (static function () use ($suiteClassFile): void {
            try {
                include_once $suiteClassFile;
            } catch (Throwable $e) {
                // If there's an error loading the file, we'll handle it below
                throw $e;
            }
        })();

        // Check if this file has inline tests
        $inlineTestSuite = InlineTestSuite::getInstance();

        if ($inlineTestSuite->hasTestsForFile($suiteClassFile)) {
            // Generate TestCase classes for inline tests in this file
            $generatedClasses = $inlineTestSuite->makeTestCasesForFile($suiteClassFile);

            if (!empty($generatedClasses)) {
                // Return the first generated class
                // PHPUnit will use this to create the test suite
                $className = $generatedClasses[0];

                /** @var class-string<TestCase> $className */
                // @phpstan-ignore return.type (ReflectionClass covariance - TestCase is subtype of object)
                return new ReflectionClass($className);
            }
        }

        // Track newly loaded classes
        $loadedClasses = array_values(
            array_diff(
                get_declared_classes(),
                array_merge(
                    self::$declaredClasses,
                    self::$loadedClasses
                )
            )
        );

        self::$loadedClasses = array_merge($loadedClasses, self::$loadedClasses);

        foreach ($loadedClasses as $loadedClass) {
            /** @var class-string $loadedClass */
            $reflection = new ReflectionClass($loadedClass);
            $filename = $reflection->getFileName();

            if ($filename !== false) {
                self::$loadedClassesByFilename[$filename] = [
                    $loadedClass,
                    ...self::$loadedClassesByFilename[$filename] ?? [],
                ];
            }
        }

        $loadedClasses = array_merge(
            self::$loadedClassesByFilename[$suiteClassFile] ?? [],
            $loadedClasses
        );

        // No classes loaded from this file - it might be empty or have only functions
        if (empty($loadedClasses)) {
            return $this->handleNoTestsFound($suiteClassName, $suiteClassFile);
        }

        // Find a TestCase class in the loaded classes
        $testCaseFound = false;
        $class = null;

        foreach (array_reverse($loadedClasses) as $loadedClass) {
            /** @var class-string $loadedClass */
            $reflection = new ReflectionClass($loadedClass);

            if ($reflection->isSubclassOf(TestCase::class)) {
                if ($reflection->isAbstract() || $suiteClassFile !== $reflection->getFileName()) {
                    continue;
                }

                $class = $reflection;
                $testCaseFound = true;
                break;
            }
        }

        if (!$testCaseFound) {
            // Try to find by class name matching
            foreach (array_reverse($loadedClasses) as $loadedClass) {
                $offset = 0 - strlen($suiteClassName);

                if (
                    stripos(substr($loadedClass, $offset - 1), '\\' . $suiteClassName) === 0 ||
                    stripos(substr($loadedClass, $offset - 1), '_' . $suiteClassName) === 0
                ) {
                    /** @var class-string $loadedClass */
                    $class = new ReflectionClass($loadedClass);
                    $testCaseFound = true;
                    break;
                }
            }
        }

        if (!$testCaseFound || $class === null) {
            // Check if expected class exists
            if (!class_exists($suiteClassName, false)) {
                return $this->handleNoTestsFound($suiteClassName, $suiteClassFile);
            }

            /** @var class-string $suiteClassName */
            $class = new ReflectionClass($suiteClassName);
        }

        // Validate the class is a proper TestCase
        if ($class->isSubclassOf(TestCase::class) && !$class->isAbstract()) {
            // @phpstan-ignore return.type (ReflectionClass covariance - TestCase is subtype of object)
            return $class;
        }

        // Check for suite() method (static test suite factory)
        if ($class->hasMethod('suite')) {
            $method = $class->getMethod('suite');

            if (!$method->isAbstract() && $method->isPublic() && $method->isStatic()) {
                // @phpstan-ignore return.type (ReflectionClass covariance - TestCase is subtype of object)
                return $class;
            }
        }

        return $this->handleNoTestsFound($suiteClassName, $suiteClassFile);
    }

    /**
     * Reload a class (required by interface but not typically used).
     *
     * @param ReflectionClass<object> $aClass
     * @return ReflectionClass<object>
     */
    public function reload(ReflectionClass $aClass): ReflectionClass
    {
        return $aClass;
    }

    /**
     * Extract the expected class name from a filename.
     */
    private function classNameFromFileName(string $suiteClassFile): string
    {
        $className = basename($suiteClassFile, '.php');
        $dotPos = strpos($className, '.');

        if ($dotPos !== false) {
            $className = substr($className, 0, $dotPos);
        }

        return $className;
    }

    /**
     * Handle the case where no tests were found in the file.
     *
     * Returns a reflection of a dummy test case that will be skipped.
     *
     * @return ReflectionClass<object>
     */
    private function handleNoTestsFound(string $suiteClassName, string $suiteClassFile): ReflectionClass
    {
        // Create a dummy class that will be ignored by PHPUnit
        // This prevents errors when scanning directories with non-test PHP files
        $dummyClassName = 'PHPUnitInline_NoTests_' . md5($suiteClassFile);

        if (!class_exists($dummyClassName, false)) {
            $code = <<<PHP
                class {$dummyClassName} extends \PHPUnit\Framework\TestCase
                {
                    public function testSkipped(): void
                    {
                        \$this->markTestSkipped('No tests found in file: {$suiteClassFile}');
                    }
                }
                PHP;

            eval($code);
        }

        /** @var class-string $dummyClassName */
        return new ReflectionClass($dummyClassName);
    }
}
