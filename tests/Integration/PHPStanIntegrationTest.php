<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for PHPStan extension.
 *
 * These tests verify that the PHPStan extension works correctly
 * with the e2e test fixtures.
 */
final class PHPStanIntegrationTest extends TestCase
{
    private static string $phpstanConfig;
    private static string $phpstanBin;

    public static function setUpBeforeClass(): void
    {
        self::$phpstanConfig = dirname(__DIR__) . '/e2e/phpstan.neon';
        self::$phpstanBin = dirname(__DIR__, 2) . '/vendor/bin/phpstan';

        if (!file_exists(self::$phpstanBin)) {
            self::markTestSkipped('PHPStan binary not found');
        }
    }

    #[Test]
    public function phpstanAnalysisPassesWithNoErrors(): void
    {
        $command = sprintf(
            '%s analyze --configuration=%s --memory-limit=256M --error-format=json 2>&1',
            escapeshellarg(self::$phpstanBin),
            escapeshellarg(self::$phpstanConfig)
        );

        exec($command, $output, $exitCode);

        $outputStr = implode("\n", $output);

        $this->assertSame(
            0,
            $exitCode,
            "PHPStan analysis should pass with no errors.\nOutput: {$outputStr}"
        );
    }

    #[Test]
    public function phpstanHasNoUnusedIgnoreRules(): void
    {
        // Run PHPStan with --debug to check for unused ignores
        // PHPStan reports unused ignores when using reportUnmatchedIgnoredErrors (default true)
        $command = sprintf(
            '%s analyze --configuration=%s --memory-limit=256M --error-format=json 2>&1',
            escapeshellarg(self::$phpstanBin),
            escapeshellarg(self::$phpstanConfig)
        );

        exec($command, $output, $exitCode);

        $outputStr = implode("\n", $output);

        // Parse JSON output to check for unmatched ignore errors
        $jsonStart = strpos($outputStr, '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($outputStr, $jsonStart);
            /** @var array{totals?: array{file_errors?: int}, files?: array<string, array{messages?: list<array{message?: string}>}>}|null $result */
            $result = json_decode($jsonStr, true);

            if (is_array($result) && isset($result['totals']['file_errors'])) {
                // Check if any errors mention "No error to ignore"
                $hasUnusedIgnores = false;
                $unusedIgnoreMessages = [];

                foreach ($result['files'] ?? [] as $file => $fileData) {
                    foreach ($fileData['messages'] ?? [] as $message) {
                        $messageText = $message['message'] ?? '';
                        if (str_contains($messageText, 'No error to ignore')) {
                            $hasUnusedIgnores = true;
                            $unusedIgnoreMessages[] = $messageText;
                        }
                    }
                }

                $this->assertFalse(
                    $hasUnusedIgnores,
                    "PHPStan config has unused ignore rules:\n" . implode("\n", $unusedIgnoreMessages)
                );
            }
        }

        // If we can't parse JSON, at least ensure analysis passed
        $this->assertSame(0, $exitCode, "PHPStan analysis should pass");
    }
}
