<?php

declare(strict_types=1);

require __DIR__ . '/tests/bootstrap.php';

use PHPUnit\InlineTests\Scanner\InlineTestScanner;

$scanner = new InlineTestScanner([__DIR__ . '/tests/Fixtures']);
$testClasses = $scanner->scan();

$found = false;
foreach ($testClasses as $testClass) {
    if ($testClass->getNamespace() === 'Acme\Math\Tests') {
        echo 'FOUND IT!' . PHP_EOL;
        echo 'Namespace: ' . $testClass->getNamespace() . PHP_EOL;
        echo 'Class: ' . $testClass->getClassName() . PHP_EOL;
        echo 'Reflection: ' . var_export($testClass->getReflection(), true) . PHP_EOL;
        echo 'Test count: ' . count($testClass->getTestMethods()) . PHP_EOL;
        $found = true;
        break;
    }
}

if (!$found) {
    echo 'NOT FOUND' . PHP_EOL;
    echo 'All namespaces:' . PHP_EOL;
    foreach ($testClasses as $testClass) {
        echo '  - ' . var_export($testClass->getNamespace(), true) . PHP_EOL;
    }
}
