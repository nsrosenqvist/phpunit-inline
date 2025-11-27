<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Fixtures;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPUnit\Framework\Attributes\Test;

/**
 * Example of class-based inline tests with state.
 */
class StateClassBased
{
    public function getValue(): int
    {
        return 42;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    // ==================== Test State ====================

    /**
     * @return array{multiplier: int, results: array<int>}
     */
    #[State]
    private static function initTestState(): array
    {
        return [
            'multiplier' => 10,
            'results' => [],
        ];
    }

    // ==================== Tests ====================

    #[Test]
    private function testStateIsAvailable(): void
    {
        /** @var array{multiplier: int, results: array<int>} $s */
        $s = state();
        test()->assertEquals(10, $s['multiplier']);
        test()->assertIsArray($s['results']);
    }

    #[Test]
    private function testCanModifyState(): void
    {
        /** @var array{multiplier: int, results: array<int>} $s */
        $s = state();
        $s['results'][] = $this->multiply(2, $s['multiplier']);
        state($s);

        /** @var array{multiplier: int, results: array<int>} $updated */
        $updated = state();
        test()->assertEquals([20], $updated['results']);
    }

    #[Test]
    private function testStateWithInstance(): void
    {
        $value = $this->getValue();
        /** @var array{multiplier: int, results: array<int>} $s */
        $s = state();

        test()->assertEquals(42, $value);
        test()->assertEquals(10, $s['multiplier']);
    }
}
