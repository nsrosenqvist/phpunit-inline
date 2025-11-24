<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Unit\TestCase;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\InlineTests\TestCase\TestProxy;
use ReflectionClass;
use ReflectionMethod;

final class TestProxyTest extends TestCase
{
    #[Test]
    public function testProxyRoutesCallsToInstanceMethods(): void
    {
        $instance = new class () {
            public function publicMethod(): string
            {
                return 'public';
            }

            private function privateMethod(): string
            {
                return 'private';
            }
        };

        $testCase = $this->createMock(TestCase::class);
        $reflection = new ReflectionMethod($instance::class, 'publicMethod');

        $proxy = new TestProxy($instance, $testCase, $reflection);

        $result = $proxy->publicMethod();
        $this->assertEquals('public', $result);
    }

    #[Test]
    public function testProxyCanAccessPrivateMethods(): void
    {
        $instance = new class () {
            private function privateMethod(): string
            {
                return 'private';
            }
        };

        $testCase = $this->createMock(TestCase::class);
        $reflection = new ReflectionMethod($instance::class, 'privateMethod');

        $proxy = new TestProxy($instance, $testCase, $reflection);

        $result = $proxy->privateMethod();
        $this->assertEquals('private', $result);
    }

    #[Test]
    public function testProxyDelegatesToTestCaseForAssertions(): void
    {
        $instance = new class () {
            public function someMethod(): string
            {
                return 'test';
            }
        };

        // Use a real TestCase instance
        $testCase = $this;

        $reflection = new ReflectionClass($instance::class);
        $testMethod = $reflection->getMethod('someMethod');

        $proxy = new TestProxy($instance, $testCase, $testMethod);

        // If this doesn't throw, the proxy successfully routed to TestCase
        $proxy->assertTrue(true);
        $proxy->assertEquals(1, 1);
        $proxy->assertIsInt(42);

        // Test passed - proxy successfully delegates to TestCase
        $this->assertTrue(true);
    }
}
