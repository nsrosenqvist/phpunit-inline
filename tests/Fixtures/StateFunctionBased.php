<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Fixtures\StateFunctionBased;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPUnit\Framework\Attributes\Test;

/**
 * State class for type-hinting.
 */
class TestState
{
    public int $counter = 0;
    public string $message = '';

    public function __construct()
    {
        $this->message = 'initialized';
    }
}

#[State]
function initState(): TestState
{
    return new TestState();
}

#[Test]
function testStateIsInitialized(): void
{
    /** @var TestState $s */
    $s = state();
    test()->assertEquals('initialized', $s->message);
    test()->assertEquals(0, $s->counter);
}

#[Test]
function testStateCanBeModified(): void
{
    /** @var TestState $s */
    $s = state();
    $s->counter++;
    state($s);

    /** @var TestState $updated */
    $updated = state();
    test()->assertEquals(1, $updated->counter);
}
