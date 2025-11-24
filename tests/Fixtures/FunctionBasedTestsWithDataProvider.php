<?php

declare(strict_types=1);

namespace Acme\DataProvider\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[Test]
#[DataProvider('additionProvider')]
function testAdditionWithProvider(int $a, int $b, int $expected): void
{
    $result = $a + $b;
    assert($result === $expected, "Expected {$expected}, got {$result}");
}

function additionProvider(): array
{
    return [
        [1, 2, 3],
        [5, 5, 10],
        [10, -5, 5],
    ];
}
