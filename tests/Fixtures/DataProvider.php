<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Fixtures;

use PHPUnit\Framework\Attributes\DataProvider as DataProviderAttribute;
use PHPUnit\Framework\Attributes\Test;

final class DataProvider
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    // ==================== Data Providers ====================

    /**
     * @return array<string, array<int>>
     */
    public static function additionProvider(): array
    {
        return [
            'positive numbers' => [2, 3, 5],
            'negative numbers' => [-2, -3, -5],
            'mixed signs' => [5, -3, 2],
            'with zero' => [0, 5, 5],
        ];
    }

    /**
     * @return array<int, array<int>>
     */
    public static function multiplicationProvider(): array
    {
        return [
            [2, 3, 6],
            [4, 5, 20],
            [0, 10, 0],
            [-2, 3, -6],
        ];
    }

    /**
     * Private static data provider
     *
     * @return array<string, array<int>>
     */
    private static function privateStaticProvider(): array
    {
        return [
            'two times two' => [2, 2, 4],
            'three times four' => [3, 4, 12],
        ];
    }

    /**
     * Private instance data provider
     *
     * @return array<int, array<int>>
     */
    private function privateInstanceProvider(): array
    {
        return [
            [5, 6, 30],
            [7, 8, 56],
        ];
    }

    // ==================== Inline Tests ====================

    #[Test]
    #[DataProviderAttribute('additionProvider')]
    private function testAddition(int $a, int $b, int $expected): void
    {
        $result = $this->add($a, $b);
        $this->assertEquals($expected, $result);
    }

    #[Test]
    #[DataProviderAttribute('multiplicationProvider')]
    private function testMultiplication(int $a, int $b, int $expected): void
    {
        $result = $this->multiply($a, $b);
        $this->assertEquals($expected, $result);
    }

    #[Test]
    private function testWithoutDataProvider(): void
    {
        // Regular test without data provider should still work
        $this->assertEquals(10, $this->add(7, 3));
    }

    #[Test]
    #[DataProviderAttribute('privateStaticProvider')]
    private function testWithPrivateStaticProvider(int $a, int $b, int $expected): void
    {
        $this->assertEquals($expected, $this->multiply($a, $b));
    }

    #[Test]
    #[DataProviderAttribute('privateInstanceProvider')]
    private function testWithPrivateInstanceProvider(int $a, int $b, int $expected): void
    {
        $this->assertEquals($expected, $this->multiply($a, $b));
    }
}
