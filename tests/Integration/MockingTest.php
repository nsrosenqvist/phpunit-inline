<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\TestCase\InlineTestCase;

use function NSRosenqvist\PHPUnitInline\test;

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
        // Create a mock using the test case
        /** @var \NSRosenqvist\PHPUnitInline\Tests\Integration\DataService&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = test()->createMock(\NSRosenqvist\PHPUnitInline\Tests\Integration\DataService::class);
        $mock->expects(test()->once())
            ->method('getData')
            ->willReturn('mocked data');

        $result = $this->processData($mock);

        test()->assertEquals('mocked data', $result);
    }

    #[Test]
    private function testCreateStubWorks(): void
    {
        // Create a stub using the test case
        /** @var \NSRosenqvist\PHPUnitInline\Tests\Integration\DataService&\PHPUnit\Framework\MockObject\Stub $stub */
        $stub = test()->createStub(\NSRosenqvist\PHPUnitInline\Tests\Integration\DataService::class);
        $stub->method('getData')
            ->willReturn('stubbed data');

        $result = $this->processData($stub);

        test()->assertEquals('stubbed data', $result);
    }
}

interface DataService
{
    public function getData(): string;
}
