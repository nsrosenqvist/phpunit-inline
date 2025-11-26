<?php

declare(strict_types=1);

/**
 * Global helper function to access the PHPUnit TestCase instance from inline tests.
 *
 * This function provides a clean way to access PHPUnit assertions and test methods
 * without confusing $this binding semantics. In class-based tests, $this refers to
 * your class instance, while test() returns the PHPUnit TestCase.
 *
 * Usage:
 *   test()->assertEquals(5, $result);
 *   test()->assertTrue($condition);
 *   test()->expectException(\Exception::class);
 *
 * @return \PHPUnit\Framework\TestCase
 * @throws \RuntimeException If called outside of an inline test context
 */
function test(): \PHPUnit\Framework\TestCase
{
    global $__inlineTestCase;

    if (!isset($__inlineTestCase) || !$__inlineTestCase instanceof \PHPUnit\Framework\TestCase) {
        throw new \RuntimeException(
            'test() can only be called from within an inline test method. ' .
            'Make sure your test has the #[Test] attribute.'
        );
    }

    return $__inlineTestCase;
}
