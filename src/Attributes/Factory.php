<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Attributes;

use Attribute;

/**
 * Specifies which factory method to use for instantiating the class under test.
 *
 * Apply this attribute to a test method to indicate which static factory method
 * should be used to create the instance. Factory methods themselves do not need
 * any attribute - they are discovered by name reference.
 *
 * Usage:
 *     #[Test]
 *     #[Factory('createWithMocks')]
 *     private function testSomething(): void { ... }
 *
 *     // Factory method (no attribute needed)
 *     private static function createWithMocks(): self { ... }
 *
 * @see DefaultFactory Use #[DefaultFactory] to mark a factory as the default
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Factory
{
    /**
     * @param string|null $methodName The name of the static factory method to use
     */
    public function __construct(
        public readonly ?string $methodName = null
    ) {
    }
}
