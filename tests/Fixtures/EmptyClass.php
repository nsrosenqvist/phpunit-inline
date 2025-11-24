<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Fixtures;

use PHPUnit\Framework\Attributes\Test;

/**
 * Class without any test methods.
 */
final class EmptyClass
{
    public function doSomething(): string
    {
        return 'something';
    }
}
