<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\Attributes\Test;

/**
 * Suppresses "unused method" warnings for methods marked with #[Test] attribute.
 *
 * @implements Rule<InClassMethodNode>
 */
final class InlineTestUnusedMethodRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * @param InClassMethodNode $node
     * @return array<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // This rule doesn't report errors - it exists to allow other rules
        // to check if a method has the #[Test] attribute and skip it
        return [];
    }

    /**
     * Check if a method has the #[Test] attribute.
     */
    public static function hasTestAttribute(\ReflectionMethod $method): bool
    {
        $attributes = $method->getAttributes(Test::class);
        return !empty($attributes);
    }
}
