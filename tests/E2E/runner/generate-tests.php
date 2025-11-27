#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generates PHPUnit test files from inline tests.
 *
 * This script scans directories for inline tests and generates proper
 * PHPUnit TestCase classes that can be run by PHPUnit's standard test runner.
 *
 * Usage: php generate-tests.php [scan-directory] [output-directory]
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use NSRosenqvist\PHPUnitInline\Scanner\InlineTestScanner;
use NSRosenqvist\PHPUnitInline\TestCase\DynamicTestCaseGenerator;

$scanDir = $argv[1] ?? __DIR__ . '/src';
$outputDir = $argv[2] ?? __DIR__ . '/generated';

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Clean existing generated files
foreach (glob($outputDir . '/*.php') as $file) {
    unlink($file);
}

echo "Scanning: $scanDir\n";

$scanner = new InlineTestScanner([$scanDir]);
$testClasses = $scanner->scan();

echo "Found " . count($testClasses) . " inline test classes\n\n";

$generator = new DynamicTestCaseGenerator();

foreach ($testClasses as $testClass) {
    $sourceClass = $testClass->getClassName();
    $generatedClassName = $generator->generate($testClass);

    // Get the generated code by capturing the eval'd class
    $reflection = new ReflectionClass($generatedClassName);

    // Since we can't get the source from eval'd classes, we need to
    // regenerate the code. Let's get it from the generator.
    $code = $generator->getGeneratedCode($testClass);

    $filename = $outputDir . '/' . $generatedClassName . '.php';
    file_put_contents($filename, $code);

    echo "Generated: $generatedClassName\n";
    echo "  Source: $sourceClass\n";
    echo "  File: $filename\n\n";
}

echo "Done! Generated " . count($testClasses) . " test files in $outputDir\n";
