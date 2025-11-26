<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline;

use NSRosenqvist\PHPUnitInline\Scanner\InlineTestClass;
use NSRosenqvist\PHPUnitInline\TestCase\DynamicTestCaseGenerator;

/**
 * Singleton that manages the inline test suite state.
 *
 * This is the central registry for inline tests, similar to Pest's TestSuite.
 * It holds configuration (scan directories) and all discovered test classes.
 */
final class TestSuite
{
    private static ?self $instance = null;

    /**
     * Root path of the project.
     */
    public readonly string $rootPath;

    /**
     * Directories to scan for inline tests.
     *
     * @var array<string>
     */
    private array $scanDirectories = [];

    /**
     * Registered test classes.
     *
     * @var array<string, InlineTestClass>
     */
    private array $testClasses = [];

    /**
     * Generated PHPUnit TestCase class names.
     *
     * @var array<string, class-string<\PHPUnit\Framework\TestCase>>
     */
    private array $generatedClasses = [];

    /**
     * The generator for creating dynamic TestCase classes.
     */
    private DynamicTestCaseGenerator $generator;

    /**
     * Private constructor for singleton pattern.
     */
    private function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->generator = new DynamicTestCaseGenerator();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(?string $rootPath = null): self
    {
        if (self::$instance === null) {
            if ($rootPath === null) {
                throw new \RuntimeException(
                    'TestSuite::getInstance() requires rootPath on first call.'
                );
            }

            self::$instance = new self($rootPath);
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (for testing purposes).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Set the directories to scan for inline tests.
     *
     * @param array<string> $directories
     */
    public function setScanDirectories(array $directories): void
    {
        // Convert relative paths to absolute paths based on rootPath
        $this->scanDirectories = array_map(function (string $dir): string {
            if (str_starts_with($dir, '/')) {
                return $dir;
            }

            return $this->rootPath . '/' . $dir;
        }, $directories);
    }

    /**
     * Get the scan directories.
     *
     * @return array<string>
     */
    public function getScanDirectories(): array
    {
        return $this->scanDirectories;
    }

    /**
     * Add a discovered test class.
     */
    public function addTestClass(InlineTestClass $testClass): void
    {
        $key = $testClass->getSourceFile() ?? $testClass->getClassName();
        $this->testClasses[$key] = $testClass;
    }

    /**
     * Get all registered test classes.
     *
     * @return array<string, InlineTestClass>
     */
    public function getTestClasses(): array
    {
        return $this->testClasses;
    }

    /**
     * Get all source files that contain inline tests.
     *
     * @return array<string>
     */
    public function getTestFilenames(): array
    {
        $filenames = [];

        foreach ($this->testClasses as $testClass) {
            $sourceFile = $testClass->getSourceFile();
            if ($sourceFile !== null && !in_array($sourceFile, $filenames, true)) {
                $filenames[] = $sourceFile;
            }
        }

        return $filenames;
    }

    /**
     * Check if a file has inline tests.
     */
    public function hasTestsForFile(string $filename): bool
    {
        $normalizedFilename = realpath($filename) ?: $filename;

        foreach ($this->testClasses as $testClass) {
            $sourceFile = $testClass->getSourceFile();
            if ($sourceFile !== null) {
                $normalizedSource = realpath($sourceFile) ?: $sourceFile;
                if ($normalizedSource === $normalizedFilename) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get test classes for a specific file.
     *
     * @return array<InlineTestClass>
     */
    public function getTestClassesForFile(string $filename): array
    {
        $normalizedFilename = realpath($filename) ?: $filename;
        $result = [];

        foreach ($this->testClasses as $testClass) {
            $sourceFile = $testClass->getSourceFile();
            if ($sourceFile !== null) {
                $normalizedSource = realpath($sourceFile) ?: $sourceFile;
                if ($normalizedSource === $normalizedFilename) {
                    $result[] = $testClass;
                }
            }
        }

        return $result;
    }

    /**
     * Generate and cache a PHPUnit TestCase class for the given InlineTestClass.
     *
     * @return class-string<\PHPUnit\Framework\TestCase>
     */
    public function makeTestCase(InlineTestClass $testClass): string
    {
        $key = $testClass->getSourceFile() ?? $testClass->getClassName();

        if (!isset($this->generatedClasses[$key])) {
            $this->generatedClasses[$key] = $this->generator->generate($testClass);
        }

        return $this->generatedClasses[$key];
    }

    /**
     * Generate TestCase classes for all tests in a file.
     *
     * @return array<class-string<\PHPUnit\Framework\TestCase>>
     */
    public function makeTestCasesForFile(string $filename): array
    {
        $testClasses = $this->getTestClassesForFile($filename);
        $result = [];

        foreach ($testClasses as $testClass) {
            $result[] = $this->makeTestCase($testClass);
        }

        return $result;
    }
}
