<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Attributes;

use Attribute;

/**
 * Marks a static method as the default factory for creating test instances.
 *
 * When a class has constructor arguments, tests need a factory to create instances.
 * The method marked with #[DefaultFactory] will be used for all tests that don't
 * explicitly specify a factory via #[Factory('methodName')].
 *
 * Usage:
 *     #[DefaultFactory]
 *     private static function createForTesting(): self
 *     {
 *         return new self(new MockDependency());
 *     }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class DefaultFactory
{
}
