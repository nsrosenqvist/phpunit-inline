<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Autoloader;

/**
 * Custom autoloader for inline test classes that don't follow PSR-4 file paths.
 *
 * This autoloader helps find namespace-based test classes (e.g., Acme\Service\Tests\ServiceTests)
 * that are defined in the same file as the application code (e.g., src/Service.php).
 *
 * It works by:
 * 1. Detecting if a class name ends with a "Tests" namespace segment
 * 2. Inferring the parent class file path based on PSR-4 conventions
 * 3. Including that file if it exists
 *
 * Example:
 *   Class: Acme\Service\Tests\ServiceTests
 *   Expected file: src/Service/Tests/ServiceTests.php (PSR-4)
 *   Actual file: src/Service.php (parent directory + file)
 */
final class InlineTestAutoloader
{
    /**
     * @var array<string, string> Map of namespace prefixes to base directories
     */
    private array $prefixes = [];

    /**
     * Register this autoloader with SPL.
     *
     * @param bool $prepend Whether to prepend this autoloader (run it first)
     */
    public function register(bool $prepend = true): void
    {
        spl_autoload_register([$this, 'loadClass'], true, $prepend);
    }

    /**
     * Unregister this autoloader from SPL.
     */
    public function unregister(): void
    {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Add a namespace prefix with its base directory.
     *
     * @param string $prefix The namespace prefix (e.g., 'Acme\\')
     * @param string $baseDir The base directory (e.g., '/path/to/src/')
     * @param bool $prepend Whether to prepend this prefix (higher priority)
     */
    public function addNamespace(string $prefix, string $baseDir, bool $prepend = false): void
    {
        // Normalize namespace prefix
        $prefix = trim($prefix, '\\') . '\\';

        // Normalize base directory
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($prepend) {
            $this->prefixes = [$prefix => $baseDir] + $this->prefixes;
        } else {
            $this->prefixes[$prefix] = $baseDir;
        }
    }

    /**
     * Load a class using inline test autoloading logic.
     *
     * @param string $class The fully qualified class name
     */
    public function loadClass(string $class): void
    {
        // Don't try to load if class already exists
        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            return;
        }

        // Try to find and include the file for this class
        $file = $this->findFileForClass($class);

        if ($file !== null && is_file($file)) {
            require_once $file;
        }
    }

    /**
     * Find the file for a class, checking parent directory patterns.
     *
     * @param string $class The fully qualified class name
     * @return string|null The file path, or null if not found
     */
    private function findFileForClass(string $class): ?string
    {
        // Check each registered namespace prefix
        foreach ($this->prefixes as $prefix => $baseDir) {
            // Does this class use this namespace prefix?
            if (str_starts_with($class, $prefix) === false) {
                continue;
            }

            // Get the relative class name (without prefix)
            $relativeClass = substr($class, strlen($prefix));

            // Check for namespace-based test pattern (e.g., Service\Tests\ServiceTests)
            $file = $this->findTestClassFile($relativeClass, $baseDir);

            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Try to find a test class file by checking parent directory patterns.
     *
     * For a class like Service\Tests\ServiceTests:
     * - PSR-4 expects: Service/Tests/ServiceTests.php
     * - We check: Service.php (parent directory as file)
     * - If not found, scan all PHP files in parent directory
     *
     * @param string $relativeClass Class name relative to namespace prefix
     * @param string $baseDir Base directory for the namespace
     * @return string|null The file path, or null if not found
     */
    private function findTestClassFile(string $relativeClass, string $baseDir): ?string
    {
        $parts = explode('\\', $relativeClass);

        // Look for "Tests" namespace segment
        $testsIndex = array_search('Tests', $parts, true);

        if ($testsIndex === false || $testsIndex === 0) {
            // No Tests namespace or it's the first segment - use standard PSR-4
            return $this->getStandardPsr4Path($relativeClass, $baseDir);
        }

        // Get the parts before "Tests" namespace
        $parentParts = array_slice($parts, 0, $testsIndex);

        // Try parent directory as file (e.g., Service\Tests\* -> Service.php)
        $parentFile = $baseDir . implode(DIRECTORY_SEPARATOR, $parentParts) . '.php';

        if (is_file($parentFile) && $this->fileContainsClass($parentFile, $relativeClass)) {
            return $parentFile;
        }

        // If direct match not found, scan the parent directory for PHP files
        // that might contain the test class (e.g., NamespaceBasedTests.php)
        $parentDir = $baseDir . (count($parentParts) > 1 ? implode(DIRECTORY_SEPARATOR, array_slice($parentParts, 0, -1)) : '');

        if (is_dir($parentDir)) {
            $candidates = $this->scanDirectoryForClassCandidates($parentDir, $parentParts[count($parentParts) - 1] ?? null);
            foreach ($candidates as $candidate) {
                if ($this->fileContainsClass($candidate, $relativeClass)) {
                    return $candidate;
                }
            }
        }

        // Fallback: try scanning base directory if parent parts is shallow
        if (count($parentParts) === 1) {
            $candidates = $this->scanDirectoryForClassCandidates($baseDir, $parentParts[0]);
            foreach ($candidates as $candidate) {
                if ($this->fileContainsClass($candidate, $relativeClass)) {
                    return $candidate;
                }
            }
        }

        // Last resort: try standard PSR-4 path
        return $this->getStandardPsr4Path($relativeClass, $baseDir);
    }

    /**
     * Scan a directory for PHP files that might contain the class.
     *
     * @param string $dir Directory to scan
     * @param string|null $classPrefix Expected class name prefix
     * @return array<string> List of candidate file paths
     */
    private function scanDirectoryForClassCandidates(string $dir, ?string $classPrefix): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = @scandir($dir);
        if ($files === false) {
            return [];
        }

        $candidates = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
                continue;
            }

            $filePath = $dir . DIRECTORY_SEPARATOR . $file;

            if (!is_file($filePath)) {
                continue;
            }

            // If we have a class prefix, prioritize files that match
            if ($classPrefix !== null && str_contains($file, $classPrefix)) {
                array_unshift($candidates, $filePath);
            } else {
                $candidates[] = $filePath;
            }
        }

        return $candidates;
    }

    /**
     * Check if a file contains a specific class by parsing tokens.
     *
     * @param string $file File path
     * @param string $relativeClass Class name relative to namespace
     * @return bool
     */
    private function fileContainsClass(string $file, string $relativeClass): bool
    {
        $contents = @file_get_contents($file);
        if ($contents === false) {
            return false;
        }

        // Quick check: does the file contain the class name at all?
        $classParts = explode('\\', $relativeClass);
        $className = end($classParts);

        if (strpos($contents, $className) === false) {
            return false;
        }

        // More thorough check: parse for the exact class in the right namespace
        // This is a simple heuristic - we look for "class ClassName"
        if (preg_match('/\bclass\s+' . preg_quote($className, '/') . '\b/', $contents)) {
            return true;
        }

        return false;
    }

    /**
     * Get the standard PSR-4 file path for a class.
     *
     * @param string $relativeClass Class name relative to namespace prefix
     * @param string $baseDir Base directory for the namespace
     * @return string|null The file path, or null if not found
     */
    private function getStandardPsr4Path(string $relativeClass, string $baseDir): ?string
    {
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        return is_file($file) ? $file : null;
    }

    /**
     * Register autoloader with namespaces from Composer's PSR-4 configuration.
     *
     * This is a convenience method that reads composer.json and registers
     * all PSR-4 namespaces automatically.
     *
     * @param string $composerJsonPath Path to composer.json
     * @return static
     */
    public static function fromComposerJson(string $composerJsonPath): self
    {
        $autoloader = new self();

        if (!is_file($composerJsonPath)) {
            return $autoloader;
        }

        $composerJson = file_get_contents($composerJsonPath);
        if ($composerJson === false) {
            return $autoloader;
        }

        $composerData = json_decode($composerJson, true);

        if (!is_array($composerData)) {
            return $autoloader;
        }

        // Register autoload PSR-4 namespaces
        if (isset($composerData['autoload']) && is_array($composerData['autoload'])
            && isset($composerData['autoload']['psr-4']) && is_array($composerData['autoload']['psr-4'])) {
            foreach ($composerData['autoload']['psr-4'] as $namespace => $path) {
                if (!is_string($path)) {
                    continue;
                }
                $baseDir = dirname($composerJsonPath) . DIRECTORY_SEPARATOR . trim($path, '/');
                $autoloader->addNamespace($namespace, $baseDir);
            }
        }

        // Also register autoload-dev PSR-4 namespaces
        if (isset($composerData['autoload-dev']) && is_array($composerData['autoload-dev'])
            && isset($composerData['autoload-dev']['psr-4']) && is_array($composerData['autoload-dev']['psr-4'])) {
            foreach ($composerData['autoload-dev']['psr-4'] as $namespace => $path) {
                if (!is_string($path)) {
                    continue;
                }
                $baseDir = dirname($composerJsonPath) . DIRECTORY_SEPARATOR . trim($path, '/');
                $autoloader->addNamespace($namespace, $baseDir);
            }
        }

        return $autoloader;
    }
}
