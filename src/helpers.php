<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline {
    /**
     * Proxy class that provides access to TestCase methods via reflection.
     *
     * This allows calling protected methods like createMock() and createStub()
     * from any scope, not just from within a TestCase subclass.
     *
     * @method void assertEquals(mixed $expected, mixed $actual, string $message = '')
     * @method void assertSame(mixed $expected, mixed $actual, string $message = '')
     * @method void assertTrue(mixed $condition, string $message = '')
     * @method void assertFalse(mixed $condition, string $message = '')
     * @method void assertNull(mixed $actual, string $message = '')
     * @method void assertNotNull(mixed $actual, string $message = '')
     * @method void assertInstanceOf(string $expected, mixed $actual, string $message = '')
     * @method void assertCount(int $expectedCount, \Countable|iterable<mixed> $haystack, string $message = '')
     * @method void assertContains(mixed $needle, iterable<mixed> $haystack, string $message = '')
     * @method void assertEmpty(mixed $actual, string $message = '')
     * @method void assertNotEmpty(mixed $actual, string $message = '')
     * @method void assertIsInt(mixed $actual, string $message = '')
     * @method void assertIsString(mixed $actual, string $message = '')
     * @method void assertIsArray(mixed $actual, string $message = '')
     * @method void assertArrayHasKey(int|string $key, array<mixed>|\ArrayAccess<mixed, mixed> $array, string $message = '')
     * @method void assertGreaterThan(mixed $expected, mixed $actual, string $message = '')
     * @method void assertLessThan(mixed $expected, mixed $actual, string $message = '')
     * @method void expectException(string $exception)
     * @method void expectExceptionMessage(string $message)
     * @method void expectExceptionCode(int|string $code)
     * @method \PHPUnit\Framework\MockObject\MockObject createMock(string $originalClassName)
     * @method \PHPUnit\Framework\MockObject\Stub createStub(string $originalClassName)
     * @method \PHPUnit\Framework\MockObject\MockBuilder<object> getMockBuilder(string $className)
     * @method \PHPUnit\Framework\MockObject\Rule\InvokedCount once()
     * @method \PHPUnit\Framework\MockObject\Rule\InvokedCount never()
     * @method \PHPUnit\Framework\MockObject\Rule\InvokedCount exactly(int $count)
     * @method \PHPUnit\Framework\MockObject\Rule\InvokedCount any()
     * @method \PHPUnit\Framework\MockObject\Rule\InvokedCount atLeastOnce()
     * @method void fail(string $message = '')
     * @method void markTestSkipped(string $message = '')
     * @method void markTestIncomplete(string $message = '')
     */
    final class TestCaseProxy
    {
        public function __construct(
            private readonly \PHPUnit\Framework\TestCase $testCase
        ) {
        }

        /**
         * Get the underlying TestCase instance.
         */
        public function getTestCase(): \PHPUnit\Framework\TestCase
        {
            return $this->testCase;
        }

        /**
         * Route method calls to the TestCase using reflection for protected methods.
         *
         * @param array<mixed> $args
         */
        public function __call(string $method, array $args): mixed
        {
            if (!method_exists($this->testCase, $method)) {
                throw new \BadMethodCallException(
                    sprintf('Method %s does not exist on TestCase', $method)
                );
            }

            $reflection = new \ReflectionMethod($this->testCase, $method);

            // Use reflection for all methods to ensure access regardless of scope
            $reflection->setAccessible(true);

            return $reflection->invoke($this->testCase, ...$args);
        }
    }

    /**
     * Global helper function to access the PHPUnit TestCase instance from inline tests.
     *
     * This function provides a clean way to access PHPUnit assertions and test methods
     * without confusing $this binding semantics. In class-based tests, $this refers to
     * your class instance, while test() returns a proxy that provides access to both
     * public and protected TestCase methods (like createMock).
     *
     * Usage:
     *   test()->assertEquals(5, $result);
     *   test()->assertTrue($condition);
     *   test()->expectException(\Exception::class);
     *   test()->createMock(SomeClass::class); // Protected methods work too!
     */
    function test(): TestCaseProxy
    {
        global $__inlineTestCase;
        global $__inlineTestCaseProxy;

        if (!isset($__inlineTestCase) || !$__inlineTestCase instanceof \PHPUnit\Framework\TestCase) {
            throw new \RuntimeException(
                'test() can only be called from within an inline test method. ' .
                'Make sure your test has the #[Test] attribute.'
            );
        }

        // Create proxy if not already created for this TestCase
        if (!isset($__inlineTestCaseProxy) || !$__inlineTestCaseProxy instanceof TestCaseProxy || $__inlineTestCaseProxy->getTestCase() !== $__inlineTestCase) {
            $__inlineTestCaseProxy = new TestCaseProxy($__inlineTestCase);
        }

        return $__inlineTestCaseProxy;
    }
}

// Also define in global namespace for convenience

namespace {
    /**
     * Global helper function to access the PHPUnit TestCase instance from inline tests.
     *
     * This is an alias for \NSRosenqvist\PHPUnitInline\test() for convenience.
     */
    function test(): \NSRosenqvist\PHPUnitInline\TestCaseProxy
    {
        return \NSRosenqvist\PHPUnitInline\test();
    }
}
