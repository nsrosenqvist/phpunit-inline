<?php

declare(strict_types=1);

namespace E2E;

use PHPUnit\Framework\Attributes\Test;
use NSRosenqvist\PHPUnitInline\Attributes\Factory;
use NSRosenqvist\PHPUnitInline\Attributes\DefaultFactory;

/**
 * Tests factory methods for classes with constructor arguments.
 */
final class WithFactory
{
    public function __construct(
        private string $prefix,
        private int $multiplier,
    ) {
    }

    public function format(string $value): string
    {
        return $this->prefix . ': ' . $value;
    }

    public function calculate(int $value): int
    {
        return $value * $this->multiplier;
    }

    #[DefaultFactory]
    private static function createDefault(): self
    {
        return new self('Default', 2);
    }

    #[Factory('createWithHighMultiplier')]
    private static function createWithHighMultiplier(): self
    {
        return new self('High', 10);
    }

    #[Test]
    private function testFormatWithDefaultFactory(): void
    {
        test()->assertEquals('Default: hello', $this->format('hello'));
    }

    #[Test]
    private function testCalculateWithDefaultFactory(): void
    {
        test()->assertEquals(10, $this->calculate(5));
    }

    #[Test]
    #[Factory('createWithHighMultiplier')]
    private function testCalculateWithHighMultiplier(): void
    {
        test()->assertEquals(50, $this->calculate(5));
    }
}
