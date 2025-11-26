<?php

declare(strict_types=1);

namespace E2E\Strip;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

// ==================== Helper Functions ====================

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text ?? '', '-');
    $text = strtolower($text);
    return preg_replace('~-+~', '-', $text) ?? '';
}

function formatPrice(float $amount): string
{
    return '$' . number_format($amount, 2);
}

// ==================== Tests ====================

#[Test]
function testSlugify(): void
{
    test()->assertEquals('hello-world', slugify('Hello World'));
}

#[Test]
#[DataProvider('priceProvider')]
function testFormatPrice(float $input, string $expected): void
{
    test()->assertEquals($expected, formatPrice($input));
}

function priceProvider(): array
{
    return [
        [10.0, '$10.00'],
        [99.99, '$99.99'],
        [0.0, '$0.00'],
    ];
}
