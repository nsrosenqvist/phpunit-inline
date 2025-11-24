<?php

declare(strict_types=1);

namespace Acme\Service;

/**
 * Example service class.
 */
final class Service
{
    public function process(string $input): string
    {
        return strtoupper($input);
    }
}

// Tests namespace with a traditional TestCase

namespace Acme\Service\Tests;

use Acme\Service\Service;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * This class EXTENDS TestCase, so it should be discovered by PHPUnit normally.
 * Our scanner should skip this class.
 */
final class ServiceTest extends TestCase
{
    #[Test]
    public function itProcessesInput(): void
    {
        $service = new Service();
        $result = $service->process('hello');

        $this->assertEquals('HELLO', $result);
    }
}
