<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Scanner;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
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
                            $testMethods
                        );
                    }
                } catch (ReflectionException) {
                    // Skip files that can't be reflected
                    continue;
                }
            }
        }

        return $testClasses;
    }

    /**
     * Extracts all fully qualified class names from a PHP file.
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
        $namespace = '';

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract all class, interface, trait, and enum names
        if (preg_match_all('/^\s*(?:final\s+|abstract\s+)?(class|interface|trait|enum)\s+(\w+)/m', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $className = $match[2];
                $fqcn = $namespace !== '' ? $namespace . '\\' . $className : $className;
                $classNames[] = $fqcn;
            }
        }

        return $classNames;
    }

    /**
     * Finds all methods in a class that have the #[Test] attribute.
     *
     * @param ReflectionClass<object> $reflection
     * @return array<ReflectionMethod>
     */
    private function findTestMethods(ReflectionClass $reflection): array
    {
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

}
