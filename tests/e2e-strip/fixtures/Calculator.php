<?php

declare(strict_types=1);

namespace E2E\Strip;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use NSRosenqvist\PHPUnitInline\Attributes\Factory;

/**
 * A sample class with inline tests to be stripped.
 */
final class Calculator
{
    public function __construct(
        private int $precision = 2
    ) {
    }

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

    public function format(float $number): string
    {
        return number_format($number, $this->precision);
    }

    // ==================== Test Support ====================

    #[Factory]
    private static function createForTesting(): self
    {
        return new self(4);
    }

    // ==================== Tests ====================

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
    #[DataProvider('additionProvider')]
    private function testAddWithDataProvider(int $a, int $b, int $expected): void
    {
        test()->assertEquals($expected, $this->add($a, $b));
    }

    public static function additionProvider(): array
    {
        return [
            'positive' => [1, 2, 3],
            'zero' => [0, 0, 0],
            'negative' => [-1, -2, -3],
        ];
    }
}
