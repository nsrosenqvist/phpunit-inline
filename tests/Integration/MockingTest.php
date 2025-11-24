<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\Scanner\InlineTestScanner;
use PHPUnit\InlineTests\TestCase\InlineTestCase;

/**
 * Test to verify that PHPUnit mocking works with inline tests.
 */
final class MockingTest extends TestCase
{
    #[Test]
    public function testCreateMockWorksInInlineTests(): void
    {
        $reflection = new \ReflectionClass(MockingTestFixture::class);
        $testMethod = $reflection->getMethod('testCreateMockWorks');

        $testCase = InlineTestCase::createTest(
            $reflection,
            $testMethod
        );

        // Execute the test - if mocking doesn't work, this will fail
        $testCase->runInlineTest();

        $this->assertTrue(true, 'createMock works in inline tests');
    }

    #[Test]
    public function testCreateStubWorksInInlineTests(): void
    {
        $reflection = new \ReflectionClass(MockingTestFixture::class);
        $testMethod = $reflection->getMethod('testCreateStubWorks');

        $testCase = InlineTestCase::createTest(
            $reflection,
            $testMethod
        );

        // Execute the test
        $testCase->runInlineTest();

        $this->assertTrue(true, 'createStub works in inline tests');
    }
}

// Test fixture class
final class MockingTestFixture
{
    public function processData(DataService $service): string
    {
        return $service->getData();
    }

    #[Test]
    private function testCreateMockWorks(): void
    {
        // Create a mock using fully qualified name
        $mock = $this->createMock(\PHPUnit\InlineTests\Tests\Integration\DataService::class);
        $mock->expects($this->once())
            ->method('getData')
            ->willReturn('mocked data');

        $result = $this->processData($mock);

        $this->assertEquals('mocked data', $result);
    }

    #[Test]
    private function testCreateStubWorks(): void
    {
        // Create a stub using fully qualified name
        $stub = $this->createStub(\PHPUnit\InlineTests\Tests\Integration\DataService::class);
        $stub->method('getData')
            ->willReturn('stubbed data');

        $result = $this->processData($stub);

        $this->assertEquals('stubbed data', $result);
    }
}

interface DataService
{
    public function getData(): string;
}
