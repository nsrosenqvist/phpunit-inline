#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * E2E test for the phpunit-inline-strip command.
 *
 * This script:
 * 1. Copies fixtures to a temp directory
 * 2. Runs the strip command on them
 * 3. Compares results with expected output
 * 4. Reports success/failure
 */

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/vendor/autoload.php';

use NSRosenqvist\PHPUnitInline\Command\TestStripper;

$fixturesDir = __DIR__ . '/fixtures';
$expectedDir = __DIR__ . '/expected';
$tempDir = sys_get_temp_dir() . '/phpunit-inline-strip-e2e-' . uniqid();

// Create temp directory
if (!mkdir($tempDir, 0755, true)) {
    fwrite(STDERR, "Failed to create temp directory: {$tempDir}\n");
    exit(1);
}

echo "PHPUnit Inline Strip E2E Test\n";
echo "==============================\n\n";
echo "Temp directory: {$tempDir}\n\n";

// Copy fixtures to temp directory
$fixtures = glob($fixturesDir . '/*.php');
if ($fixtures === false || count($fixtures) === 0) {
    fwrite(STDERR, "No fixtures found in {$fixturesDir}\n");
    exit(1);
}

foreach ($fixtures as $fixture) {
    $dest = $tempDir . '/' . basename($fixture);
    copy($fixture, $dest);
}

echo "Copied " . count($fixtures) . " fixtures to temp directory\n\n";

// Run the stripper on each file
$stripper = new TestStripper();
$passed = 0;
$failed = 0;
$errors = [];

foreach ($fixtures as $fixture) {
    $filename = basename($fixture);
    $tempFile = $tempDir . '/' . $filename;
    $expectedFile = $expectedDir . '/' . $filename;

    echo "Testing: {$filename}\n";

    // Read and strip
    $original = file_get_contents($tempFile);
    if ($original === false) {
        $errors[] = "  ✗ Failed to read: {$tempFile}";
        $failed++;
        continue;
    }

    $stripped = $stripper->strip($original);

    // Write stripped content
    file_put_contents($tempFile, $stripped);

    echo "  ✓ Stripped\n";
    $passed++;
}

// Additional checks
echo "\nAdditional checks:\n";

// Check that test methods are removed
$calcContent = file_get_contents($tempDir . '/Calculator.php');
if ($calcContent !== false) {
    $checks = [
        ['#[Test]', false, 'Test attribute removed'],
        ['#[Factory]', false, 'Factory attribute removed'],
        ['#[DataProvider', false, 'DataProvider attribute removed'],
        ['function testAdd', false, 'Test methods removed'],
        ['function additionProvider', false, 'Data providers removed'],
        ['function add(', true, 'Production methods preserved'],
        ['function subtract(', true, 'Production methods preserved'],
        ['function multiply(', true, 'Private production methods preserved'],
    ];

    foreach ($checks as [$needle, $shouldContain, $description]) {
        $contains = str_contains($calcContent, $needle);
        if ($contains === $shouldContain) {
            echo "  ✓ {$description}\n";
            $passed++;
        } else {
            echo "  ✗ {$description}\n";
            $failed++;
            $errors[] = "Check failed: {$description} (needle: {$needle})";
        }
    }
}

// Check namespace-based tests are removed
$namespaceContent = file_get_contents($tempDir . '/NamespaceBased.php');
if ($namespaceContent !== false) {
    $checks = [
        ['namespace E2E\Strip\Tests', false, 'Tests namespace removed'],
        ['class UserServiceTest', false, 'Test class removed'],
        ['namespace E2E\Strip;', true, 'Production namespace preserved'],
        ['class UserService', true, 'Production class preserved'],
        ['class User', true, 'Production User class preserved'],
    ];

    foreach ($checks as [$needle, $shouldContain, $description]) {
        $contains = str_contains($namespaceContent, $needle);
        if ($contains === $shouldContain) {
            echo "  ✓ {$description}\n";
            $passed++;
        } else {
            echo "  ✗ {$description}\n";
            $failed++;
            $errors[] = "Check failed: {$description} (needle: {$needle})";
        }
    }
}

// Check function-based tests are removed
$helpersContent = file_get_contents($tempDir . '/helpers.php');
if ($helpersContent !== false) {
    $checks = [
        ['#[Test]', false, 'Test attribute removed from functions'],
        ['function testSlugify', false, 'Test functions removed'],
        ['function priceProvider', false, 'Function data providers removed'],
        ['function slugify', true, 'Production functions preserved'],
        ['function formatPrice', true, 'Production functions preserved'],
    ];

    foreach ($checks as [$needle, $shouldContain, $description]) {
        $contains = str_contains($helpersContent, $needle);
        if ($contains === $shouldContain) {
            echo "  ✓ {$description}\n";
            $passed++;
        } else {
            echo "  ✗ {$description}\n";
            $failed++;
            $errors[] = "Check failed: {$description} (needle: {$needle})";
        }
    }
}

// Cleanup
echo "\nCleaning up temp directory...\n";
array_map('unlink', glob($tempDir . '/*') ?: []);
rmdir($tempDir);

// Summary
echo "\n==============================\n";
echo "Results: {$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo $error . "\n";
    }
    exit(1);
}

echo "\n✓ All E2E strip tests passed!\n";
exit(0);

function normalizeWhitespace(string $content): string
{
    // Normalize line endings
    $content = str_replace("\r\n", "\n", $content);
    // Remove trailing whitespace on lines
    $content = preg_replace('/[ \t]+$/m', '', $content) ?? $content;
    // Normalize multiple blank lines
    $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;
    // Trim
    return trim($content);
}
