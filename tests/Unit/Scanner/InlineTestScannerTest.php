<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Unit\Scanner;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\Tests\Fixtures\Calculator;

final class InlineTestScannerTest extends TestCase
{
    #[Test]
    public function testScanFindsTestMethodsInFixtures(): void
    {
        $scanner = new InlineTestScanner([__DIR__ . '/../../Fixtures']);
        $testClasses = $scanner->scan();

        $this->assertNotEmpty($testClasses);

        // Find the Calculator class
        $calculatorClass = null;
        foreach ($testClasses as $testClass) {
            if ($testClass->getClassName() === Calculator::class) {
                $calculatorClass = $testClass;
                break;
            }
        }

        $this->assertNotNull($calculatorClass, 'Calculator class should be found');
        $this->assertCount(5, $calculatorClass->getTestMethods(), 'Calculator should have 5 test methods');
    }

    #[Test]
    public function testScanReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $scanner = new InlineTestScanner(['/non/existent/directory']);
        $testClasses = $scanner->scan();

        $this->assertIsArray($testClasses);
        $this->assertEmpty($testClasses);
    }

    #[Test]
    public function testScanReturnsEmptyArrayForDirectoryWithoutTests(): void
    {
        // Create a temp directory with a PHP file without tests
        $tempDir = sys_get_temp_dir() . '/phpunit-inline-tests-' . uniqid();
        mkdir($tempDir);

        file_put_contents(
            $tempDir . '/NoTests.php',
            '<?php class NoTests { public function foo() {} }'
        );

        $scanner = new InlineTestScanner([$tempDir]);
        $testClasses = $scanner->scan();

        $this->assertEmpty($testClasses);

        // Cleanup
        unlink($tempDir . '/NoTests.php');
        rmdir($tempDir);
    }
}
