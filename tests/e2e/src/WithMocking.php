<?php

declare(strict_types=1);

namespace E2E;

use PHPUnit\Framework\Attributes\Test;

/**
 * Tests mocking support in inline tests.
 */
final class WithMocking
{
    public function __construct(
        private ?DependencyInterface $dependency = null,
    ) {
    }

    public function process(): string
    {
        if ($this->dependency === null) {
            return 'no dependency';
        }

        return $this->dependency->getValue();
    }

    #[Test]
    private function testCreateMock(): void
    {
        $mock = test()->createMock(\E2E\DependencyInterface::class);
        $mock->expects(test()->once())
            ->method('getValue')
            ->willReturn('mocked');

        $instance = new self($mock);
        test()->assertEquals('mocked', $instance->process());
    }

    #[Test]
    private function testCreateStub(): void
    {
        $stub = test()->createStub(\E2E\DependencyInterface::class);
        $stub->method('getValue')->willReturn('stubbed');

        $instance = new self($stub);
        test()->assertEquals('stubbed', $instance->process());
    }
}

interface DependencyInterface
{
    public function getValue(): string;
}
