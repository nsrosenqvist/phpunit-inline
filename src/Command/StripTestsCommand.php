<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Command to strip inline tests from source files.
 *
 * Removes:
 * - Methods/functions with #[Test] attribute
 * - Methods/functions with #[Before], #[After], #[BeforeClass], #[AfterClass] attributes
 * - Methods with #[Factory] or #[DefaultFactory] attributes
 * - Data provider methods/functions referenced by tests
 * - Entire \Tests sub-namespaces
 * - Test-related use statements
 * - Test section comments
 */
final class StripTestsCommand
{
    private bool $verbose = false;
    private bool $dryRun = false;

    /**
     * Track files that failed linting (original path => error message).
     *
     * @var array<string, string>
     */
    private array $failedFiles = [];

    /**
     * @param array<string> $argv
     */
    public function run(array $argv): int
    {
        $directories = [];

        // Parse arguments
        array_shift($argv); // Remove script name

        foreach ($argv as $arg) {
            if ($arg === '-v' || $arg === '--verbose') {
                $this->verbose = true;
            } elseif ($arg === '--dry-run') {
                $this->dryRun = true;
            } elseif ($arg === '-h' || $arg === '--help') {
                $this->showHelp();
                return 0;
            } elseif (!str_starts_with($arg, '-')) {
                $directories[] = $arg;
            }
        }

        if (empty($directories)) {
            fwrite(STDERR, "Error: No directories specified\n\n");
            $this->showHelp();
            return 1;
        }

        if ($this->dryRun) {
            $this->log("Dry run mode - no files will be modified\n");
        }

        $totalStripped = 0;
        $this->failedFiles = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                fwrite(STDERR, "Warning: Directory not found: {$directory}\n");
                continue;
            }

            $stripped = $this->processDirectory($directory);
            $totalStripped += $stripped;
        }

        $this->log("\nTotal files processed: {$totalStripped}\n");

        if (count($this->failedFiles) > 0) {
            fwrite(STDERR, "\nSyntax errors detected in " . count($this->failedFiles) . " file(s):\n");

            foreach ($this->failedFiles as $filePath => $error) {
                $stripFile = $filePath . '.strip';
                fwrite(STDERR, "  {$filePath}: {$error}\n");
                fwrite(STDERR, "    Failed output saved to: {$stripFile}\n");
            }

            fwrite(STDERR, "\nOriginal files have been preserved. Review the .strip files to investigate.\n");

            return 1;
        }

        if ($totalStripped > 0 && !$this->dryRun) {
            $this->log("All modified files passed syntax validation.\n", true);
        }

        return 0;
    }

    private function showHelp(): void
    {
        echo <<<HELP
PHPUnit Inline Test Stripper

Usage: phpunit-inline-strip [options] <directory> [<directory> ...]

Options:
  -v, --verbose    Show detailed output
  --dry-run        Preview changes without modifying files
  -h, --help       Show this help message

Example:
  vendor/bin/phpunit-inline-strip src/
  vendor/bin/phpunit-inline-strip --dry-run src/ app/

WARNING: This command permanently modifies files. Only run during
container image builds or deployment pipelines. Never run on your
development environment.

Each file is validated using PHP's syntax checker (php -l) before
replacing the original. If validation fails, the original file is
preserved and the stripped version is saved as [filename].php.strip
for investigation.

HELP;
    }

    private function processDirectory(string $directory): int
    {
        $this->log("Processing directory: {$directory}\n");

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($this->processFile($file->getPathname())) {
                $count++;
            }
        }

        return $count;
    }

    private function processFile(string $filePath): bool
    {
        $originalContent = file_get_contents($filePath);
        if ($originalContent === false) {
            fwrite(STDERR, "Warning: Could not read file: {$filePath}\n");
            return false;
        }

        $stripper = new TestStripper();
        $strippedContent = $stripper->strip($originalContent);

        if ($strippedContent === $originalContent) {
            return false;
        }

        $this->log("  Stripping: {$filePath}\n", true);

        if ($this->dryRun) {
            return true;
        }

        // Write stripped content to temporary file for validation
        $stripFile = $filePath . '.strip';
        file_put_contents($stripFile, $strippedContent);

        // Validate the stripped file
        $error = $this->lintFile($stripFile);

        if ($error !== null) {
            // Linting failed - keep original, leave .strip file for investigation
            $this->failedFiles[$filePath] = $error;
            return true;
        }

        // Linting passed - replace original with stripped version
        rename($stripFile, $filePath);

        return true;
    }

    /**
     * Run PHP syntax check on a file.
     *
     * @return string|null Error message if syntax is invalid, null if valid
     */
    private function lintFile(string $filePath): ?string
    {
        $output = [];
        $exitCode = 0;

        exec(
            sprintf('%s -l %s 2>&1', PHP_BINARY, escapeshellarg($filePath)),
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            // Extract the error message, filtering out noise
            $errorLines = array_filter(
                $output,
                fn ($line) => !str_contains($line, 'No syntax errors') && trim($line) !== ''
            );
            return implode(' ', $errorLines);
        }

        return null;
    }

    private function log(string $message, bool $verboseOnly = false): void
    {
        if ($verboseOnly && !$this->verbose) {
            return;
        }
        echo $message;
    }
}
