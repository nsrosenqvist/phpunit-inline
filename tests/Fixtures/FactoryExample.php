<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Fixtures;

use PHPUnit\Framework\Attributes\Test;
use NSRosenqvist\PHPUnitInline\Attributes\DefaultFactory;
use NSRosenqvist\PHPUnitInline\Attributes\Factory;

/**
 * Example class with constructor arguments that requires factory methods for testing.
 */
final class FactoryExample
{
    public function __construct(
        private readonly string $prefix,
        private readonly int $multiplier
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

    // ==================== Factories ====================

    #[DefaultFactory]
    private static function createDefault(): self
    {
        return new self('Default', 2);
    }

    private static function createWithCustomPrefix(): self
    {
        return new self('Custom', 3);
    }

    private static function createWithHighMultiplier(): self
    {
        return new self('High', 10);
    }

    // ==================== Tests ====================

    #[Test]
    private function testFormatWithDefaultFactory(): void
    {
        // Uses default factory - prefix should be "Default"
        $result = $this->format('test');
        test()->assertEquals('Default: test', $result);
    }

    #[Test]
    private function testCalculateWithDefaultFactory(): void
    {
        // Uses default factory - multiplier should be 2
        $result = $this->calculate(5);
        test()->assertEquals(10, $result);
    }

    #[Test]
    #[Factory('createWithCustomPrefix')]
    private function testFormatWithCustomFactory(): void
    {
        // Uses custom factory - prefix should be "Custom"
        $result = $this->format('test');
        test()->assertEquals('Custom: test', $result);
    }

    #[Test]
    #[Factory('createWithHighMultiplier')]
    private function testCalculateWithHighMultiplier(): void
    {
        // Uses high multiplier factory - multiplier should be 10
        $result = $this->calculate(5);
        test()->assertEquals(50, $result);
    }
}
