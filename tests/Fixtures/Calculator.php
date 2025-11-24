<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Fixtures;

use PHPUnit\Framework\Attributes\Test;

/**
 * Example application class with inline tests for testing the extension.
 */
final class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    private function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    protected function divide(int $a, int $b): int
    {
        if ($b === 0) {
            throw new \InvalidArgumentException('Cannot divide by zero');
        }

        return intdiv($a, $b);
    }

    #[Test]
    private function testAdd(): void
    {
        $result = $this->add(2, 3);
        $this->assertEquals(5, $result);
    }

    #[Test]
    private function testSubtract(): void
    {
        $result = $this->subtract(10, 3);
        $this->assertEquals(7, $result);
    }

    #[Test]
    private function testMultiplyPrivateMethod(): void
    {
        // This tests that we can access private methods
        $result = $this->multiply(4, 5);
        $this->assertEquals(20, $result);
    }

    #[Test]
    protected function testDivideProtectedMethod(): void
    {
        // This tests that we can access protected methods
        $result = $this->divide(20, 4);
        $this->assertEquals(5, $result);
    }

    #[Test]
    private function testDivideByZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot divide by zero');

        $this->divide(10, 0);
    }
}
