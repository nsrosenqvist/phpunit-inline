<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Attributes;

use Attribute;

/**
 * Marks a function or static method as a state initializer for inline tests.
 *
 * The marked function/method is called once before all tests in the test case
 * (like #[BeforeClass]) and its return value becomes the shared state accessible
 * via the state() helper function.
 *
 * For function-based tests in a \Tests namespace:
 * ```php
 * namespace App\Services\Tests;
 *
 * #[State]
 * function initState(): TestState
 * {
 *     return new TestState(
 *         db: new PDO('sqlite::memory:'),
 *         service: new UserService(),
 *     );
 * }
 *
 * #[Test]
 * function testSomething(): void
 * {
 *     state()->service->doSomething();
 *     test()->assertTrue(true);
 * }
 * ```
 *
 * For class-based inline tests:
 * ```php
 * class Calculator
 * {
 *     #[State]
 *     private static function initTestState(): array
 *     {
 *         return ['value' => 42];
 *     }
 *
 *     #[Test]
 *     private function testSomething(): void
 *     {
 *         test()->assertEquals(42, state()['value']);
 *     }
 * }
 * ```
 *
 * State is:
 * - Initialized once per test case (before all tests)
 * - Shared across all tests in the test case
 * - Mutable - tests can modify it via state($newState)
 * - NOT reset between individual tests
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
final class State
{
}
