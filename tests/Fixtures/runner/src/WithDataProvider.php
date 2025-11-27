<?php

declare(strict_types=1);

namespace E2E;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests data providers with inline tests.
 */
final class WithDataProvider
{
    public function format(string $input): string
    {
        return strtoupper(trim($input));
    }

    #[Test]
    #[DataProvider('formatProvider')]
    private function testFormat(string $input, string $expected): void
    {
        test()->assertEquals($expected, $this->format($input));
    }

    public static function formatProvider(): array
    {
        return [
            'simple' => ['hello', 'HELLO'],
            'with spaces' => ['  world  ', 'WORLD'],
            'mixed case' => ['HeLLo', 'HELLO'],
        ];
    }

    #[Test]
    private function testFormatSimple(): void
    {
        test()->assertEquals('TEST', $this->format('test'));
    }
}
