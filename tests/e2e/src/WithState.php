<?php

declare(strict_types=1);

namespace E2E;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPUnit\Framework\Attributes\Test;

/**
 * State class for type-safe state management.
 */
class CalculatorTestState
{
    public int $multiplier = 10;
    /** @var array<int> */
    public array $results = [];

    public function addResult(int $result): void
    {
        $this->results[] = $result;
    }

    public function getLastResult(): ?int
    {
        return $this->results[array_key_last($this->results)] ?? null;
    }
}

/**
 * Calculator with state management - tests PHPStan state() type inference.
 */
final class WithState
{
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    #[State]
    private static function initTestState(): CalculatorTestState
    {
        return new CalculatorTestState();
    }

    #[Test]
    private function testStateTypeIsInferred(): void
    {
        // PHPStan should know state() returns CalculatorTestState
        // without needing a @var annotation
        $s = state();

        // These property accesses should not cause PHPStan errors
        test()->assertEquals(10, $s->multiplier);
        test()->assertIsArray($s->results);
    }

    #[Test]
    private function testStateMethodsWork(): void
    {
        $s = state();

        // Method calls should work without type errors
        $result = $this->multiply(5, $s->multiplier);
        $s->addResult($result);

        test()->assertEquals(50, $result);
        test()->assertEquals(50, $s->getLastResult());
    }

    #[Test]
    private function testStateCanBeModified(): void
    {
        $s = state();
        $s->multiplier = 20;
        state($s);

        $updated = state();
        test()->assertEquals(20, $updated->multiplier);
    }
}
