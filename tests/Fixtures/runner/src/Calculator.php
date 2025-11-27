<?php

declare(strict_types=1);

namespace E2E;

use PHPUnit\Framework\Attributes\Test;

/**
 * A simple calculator class with inline tests - tests basic inline testing.
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
        test()->assertEquals(5, $result);
    }

    #[Test]
    private function testSubtract(): void
    {
        $result = $this->subtract(10, 3);
        test()->assertEquals(7, $result);
    }

    #[Test]
    private function testMultiplyPrivateMethod(): void
    {
        $result = $this->multiply(4, 5);
        test()->assertEquals(20, $result);
    }

    #[Test]
    private function testDivideProtectedMethod(): void
    {
        $result = $this->divide(20, 4);
        test()->assertEquals(5, $result);
    }
}
