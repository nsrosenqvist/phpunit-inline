<?php

require __DIR__ . '/vendor/autoload.php';

use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\InlineTestSuiteBuilder;

$scanner = new InlineTestScanner([__DIR__ . '/tests/Fixtures']);
$testClasses = $scanner->scan(__DIR__ . '/tests/Fixtures/FunctionBasedTestsWithDataProvider.php');

foreach ($testClasses as $testClass) {
    if ($testClass->isFunctionBased() && $testClass->getNamespace() === 'Acme\\DataProvider\\Tests') {
        echo "Building suite for: " . $testClass->getNamespace() . "\n";
        
        $builder = new InlineTestSuiteBuilder();
        $suite = $builder->build([$testClass]);
        
        $tests = $suite->tests();
        echo "Number of test suites: " . count($tests) . "\n";
        
        if (!empty($tests)) {
            $functionSuite = $tests[0];
            echo "Number of test cases in suite: " . count($functionSuite->tests()) . "\n";
            
            foreach ($functionSuite->tests() as $test) {
                echo "  - " . $test->name() . "\n";
            }
        }
    }
}
