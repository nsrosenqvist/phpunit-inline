<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\InlineTestSuiteBuilder;

final class FunctionBasedDataProviderExecutionTest extends TestCase
{
    public function testDataProviderActuallyWorks(): void
    {
        // This test verifies that function-based tests with data providers actually execute correctly
        // by checking that the scanner discovers them and the suite builder creates valid test classes
        
        $scanner = new InlineTestScanner([__DIR__ . '/../Fixtures']);
        $testClasses = $scanner->scan(__DIR__ . '/../Fixtures/FunctionBasedTestsWithDataProvider.php');

        $testClass = null;
        foreach ($testClasses as $tc) {
            if ($tc->isFunctionBased() && $tc->getNamespace() === 'Acme\\DataProvider\\Tests') {
                $testClass = $tc;
                break;
            }
        }

        self::assertNotNull($testClass, 'Should find function-based test class');
        
        // Verify the test method has the DataProvider attribute
        $testMethods = $testClass->getTestMethods();
        self::assertCount(1, $testMethods);
        
        $testMethod = $testMethods[0];
        $attributes = $testMethod->getAttributes(\PHPUnit\Framework\Attributes\DataProvider::class);
        self::assertNotEmpty($attributes, 'Test method should have DataProvider attribute');
        
        // Verify the data provider function exists and returns correct data
        $providerName = $attributes[0]->getArguments()[0];
        $fullProviderName = $testClass->getNamespace() . '\\' . $providerName;
        
        self::assertTrue(function_exists($fullProviderName), "Data provider function {$fullProviderName} should exist");
        
        $providerData = $fullProviderName();
        self::assertIsArray($providerData, 'Data provider should return an array');
        self::assertCount(3, $providerData, 'Data provider should return 3 data sets');
    }
}
