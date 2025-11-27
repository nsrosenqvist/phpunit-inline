<?php

// tests/e2e/src/functions.php

declare(strict_types=1);

namespace E2E\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

// ==================== Helper Functions ====================

function add(int $a, int $b): int
{
    return $a + $b;
}

function multiply(int $a, int $b): int
{
    return $a * $b;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text ?? '', '-');
    $text = strtolower($text);
    return preg_replace('~-+~', '-', $text) ?? '';
}

// ==================== Tests ====================

#[Test]
function testAdd(): void
{
    test()->assertEquals(5, add(2, 3));
    test()->assertEquals(0, add(-2, 2));
}

#[Test]
function testMultiply(): void
{
    test()->assertEquals(20, multiply(4, 5));
    test()->assertEquals(0, multiply(0, 100));
}

#[Test]
#[DataProvider('slugifyProvider')]
function testSlugify(string $input, string $expected): void
{
    test()->assertEquals($expected, slugify($input));
}

function slugifyProvider(): array
{
    return [
        ['Hello World', 'hello-world'],
        ['PHP is GREAT', 'php-is-great'],
        ['Multiple   Spaces', 'multiple-spaces'],
    ];
}
