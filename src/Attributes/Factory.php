<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Attributes;

use Attribute;

/**
 * Marks a static method as a factory for creating test instances.
 *
 * When applied to a test method, specifies which factory to use.
 * When applied to a static method without an argument, marks it as a factory.
 *
 * Usage on test method (specify factory to use):
 *     #[Test]
 *     #[Factory('createWithMocks')]
 *     private function testSomething(): void { ... }
 *
 * Usage on factory method (declare as factory):
 *     #[Factory]
 *     private static function createWithMocks(): self { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Factory
{
    /**
     * @param string|null $methodName The factory method name to use (when on test method),
     *                                or null (when declaring a factory method)
     */
    public function __construct(
        public readonly ?string $methodName = null
    ) {
    }
}
