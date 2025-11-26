<?php

declare(strict_types=1);

namespace E2E;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\After;

/**
 * Tests lifecycle methods (#[Before], #[After]) with inline tests.
 */
final class WithLifecycle
{
    private int $counter = 0;

    #[Before]
    private function setUp(): void
    {
        $this->counter = 10;
    }

    #[After]
    private function tearDown(): void
    {
        $this->counter = 0;
    }

    #[Test]
    private function testCounterIsInitialized(): void
    {
        test()->assertEquals(10, $this->counter);
    }

    #[Test]
    private function testCounterCanBeModified(): void
    {
        $this->counter += 5;
        test()->assertEquals(15, $this->counter);
    }
}
