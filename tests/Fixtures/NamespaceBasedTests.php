<?php

declare(strict_types=1);

namespace Acme\Calculator;

/**
 * Example production class with tests in a nested namespace.
 * This demonstrates Rust-style mod tests pattern.
 */
final class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}

// Tests namespace - similar to Rust's "mod tests"

namespace Acme\Calculator\Tests;

use Acme\Calculator\Calculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for Calculator using namespace-based organization.
 * This class does NOT extend TestCase, so it will use inline execution.
 */
final class CalculatorTests
{
    #[Test]
    public function itAddsNumbers(): void
    {
        $calculator = new Calculator();
        $result = $calculator->add(2, 3);

        $this->assertEquals(5, $result);
    }

    #[Test]
    public function itMultipliesNumbers(): void
    {
        $calculator = new Calculator();
        $result = $calculator->multiply(4, 5);

        $this->assertEquals(20, $result);
    }

    #[Test]
    #[DataProvider('additionProvider')]
    public function itAddsWithDataProvider(int $a, int $b, int $expected): void
    {
        $calculator = new Calculator();
        $result = $calculator->add($a, $b);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function additionProvider(): array
    {
        return [
            'positive numbers' => [1, 2, 3],
            'negative numbers' => [-1, -2, -3],
            'mixed signs' => [5, -3, 2],
        ];
    }
}
