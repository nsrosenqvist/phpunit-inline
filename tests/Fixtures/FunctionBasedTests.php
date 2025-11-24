<?php

declare(strict_types=1);

namespace Acme\Math;

function add(int $a, int $b): int
{
    return $a + $b;
}

function multiply(int $a, int $b): int
{
    return $a * $b;
}

// Function-based tests in a nested namespace

namespace Acme\Math\Tests;

use PHPUnit\Framework\Attributes\Test;

use function Acme\Math\add;
use function Acme\Math\multiply;

#[Test]
function testAdd(): void
{
    $result = add(2, 3);
    $this->assertEquals(5, $result);
}

#[Test]
function testMultiply(): void
{
    $result = multiply(4, 5);
    $this->assertEquals(20, $result);
}

#[Test]
function testAddNegative(): void
{
    $result = add(-2, -3);
    $this->assertEquals(-5, $result);
}
