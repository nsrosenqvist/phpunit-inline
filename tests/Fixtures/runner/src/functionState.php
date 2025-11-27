<?php

declare(strict_types=1);

namespace E2E\FunctionState;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPUnit\Framework\Attributes\Test;

/**
 * State class for function-based tests.
 */
class CounterState
{
    public int $value = 0;

    public function increment(): void
    {
        $this->value++;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

#[State]
function initState(): CounterState
{
    return new CounterState();
}

#[Test]
function testStateTypeInferredInFunctions(): void
{
    // PHPStan should infer that state() returns CounterState
    $s = state();

    // Property access should work without @var annotation
    test()->assertEquals(0, $s->value);
}

#[Test]
function testStateMethodCallsWork(): void
{
    $s = state();

    // Method calls should work
    $s->increment();
    test()->assertEquals(1, $s->getValue());
}
