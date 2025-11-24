<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\Tests\Fixtures;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test fixture for verifying lifecycle method attributes work with inline tests.
 */
final class LifecycleMethods
{
    /** @var array<string> */
    public static array $executionLog = [];

    private int $value = 0;

    #[BeforeClass]
    public static function setUpBeforeClass(): void
    {
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog = [];
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog[] = 'beforeClass';
    }

    #[Before]
    public function setUp(): void
    {
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog[] = 'before';
        $this->value = 10;
    }

    #[Test]
    public function testOne(): void
    {
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog[] = 'testOne';
        $this->assertEquals(10, $this->value);
        $this->value = 20;
    }

    #[Test]
    public function testTwo(): void
    {
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog[] = 'testTwo';
        // Value should be reset to 10 by setUp
        $this->assertEquals(10, $this->value);
    }

    #[After]
    public function tearDown(): void
    {
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog[] = 'after';
    }

    #[AfterClass]
    public static function tearDownAfterClass(): void
    {
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog[] = 'afterClass';
    }

    public static function resetLog(): void
    {
        \PHPUnit\InlineTests\Tests\Fixtures\LifecycleMethods::$executionLog = [];
    }
}
