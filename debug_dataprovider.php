<?php

require __DIR__ . '/vendor/autoload.php';

use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\DynamicTestCaseGenerator;

$scanner = new InlineTestScanner([__DIR__ . '/tests/Fixtures']);
$testClasses = $scanner->scan(__DIR__ . '/tests/Fixtures/FunctionBasedTestsWithDataProvider.php');

foreach ($testClasses as $testClass) {
    if ($testClass->isFunctionBased()) {
        echo "Found function-based test class in namespace: " . $testClass->getNamespace() . "\n";
        echo "Test methods: " . count($testClass->getTestMethods()) . "\n\n";
        
        $generator = new DynamicTestCaseGenerator();
        
        // Use reflection to call the private buildClassCode method
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('buildClassCode');
        $method->setAccessible(true);
        
        $className = 'DebugTest_' . uniqid();
        $code = $method->invoke($generator, $className, $testClass);
        
        echo "Generated code:\n";
        echo "================\n";
        echo $code;
        echo "================\n";
    }
}
