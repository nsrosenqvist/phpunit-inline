<?php

declare(strict_types=1);

namespace E2E\Strip;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\After;

/**
 * A class demonstrating lifecycle methods to be stripped.
 */
final class WithLifecycle
{
    private array $items = [];

    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem(string $item): void
    {
        $this->items[] = $item;
    }

    // ==================== Tests ====================

    #[Before]
    private function setUp(): void
    {
        $this->items = ['initial'];
    }

    #[After]
    private function tearDown(): void
    {
        $this->items = [];
    }

    #[Test]
    private function testAddItem(): void
    {
        $this->addItem('test');
        test()->assertCount(2, $this->items);
    }
}
