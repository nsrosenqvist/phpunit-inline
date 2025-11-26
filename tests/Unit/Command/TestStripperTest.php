<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Unit\Command;

use NSRosenqvist\PHPUnitInline\Command\TestStripper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestStripperTest extends TestCase
{
    private TestStripper $stripper;

    protected function setUp(): void
    {
        $this->stripper = new TestStripper();
    }

    #[Test]
    public function testStripsTestMethodsWithTestAttribute(): void
    {
        $input = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    #[Test]
    private function testAdd(): void
    {
        test()->assertEquals(5, $this->add(2, 3));
    }
}
PHP;

        $expected = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
PHP;

        $result = $this->stripper->strip($input);
        $this->assertSame($this->normalizeWhitespace($expected), $this->normalizeWhitespace($result));
    }

    #[Test]
    public function testStripsBeforeAndAfterMethods(): void
    {
        $input = <<<'PHP'
<?php

class MyTest
{
    private array $data = [];

    #[Before]
    public function setUp(): void
    {
        $this->data = ['test'];
    }

    #[After]
    public function tearDown(): void
    {
        $this->data = [];
    }

    public function doSomething(): void
    {
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringNotContainsString('#[Before]', $result);
        $this->assertStringNotContainsString('#[After]', $result);
        $this->assertStringNotContainsString('setUp', $result);
        $this->assertStringNotContainsString('tearDown', $result);
        $this->assertStringContainsString('doSomething', $result);
    }

    #[Test]
    public function testStripsFactoryMethods(): void
    {
        $input = <<<'PHP'
<?php

class Service
{
    public function __construct(private Dep $dep)
    {
    }

    public function run(): void
    {
    }

    #[Factory]
    private static function create(): self
    {
        return new self(new Dep());
    }

    #[DefaultFactory]
    private static function createDefault(): self
    {
        return new self(new MockDep());
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringNotContainsString('#[Factory]', $result);
        $this->assertStringNotContainsString('#[DefaultFactory]', $result);
        $this->assertStringNotContainsString('function create', $result);
        $this->assertStringNotContainsString('function createDefault', $result);
        $this->assertStringContainsString('function run', $result);
    }

    #[Test]
    public function testStripsDataProviderMethods(): void
    {
        $input = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    #[Test]
    #[DataProvider('addProvider')]
    private function testAdd(int $a, int $b, int $expected): void
    {
        test()->assertEquals($expected, $this->add($a, $b));
    }

    public static function addProvider(): array
    {
        return [
            [1, 2, 3],
            [0, 0, 0],
        ];
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringNotContainsString('#[Test]', $result);
        $this->assertStringNotContainsString('#[DataProvider', $result);
        $this->assertStringNotContainsString('testAdd', $result);
        $this->assertStringNotContainsString('addProvider', $result);
        $this->assertStringContainsString('function add', $result);
    }

    #[Test]
    public function testStripsTestsNamespace(): void
    {
        $input = <<<'PHP'
<?php

namespace App\Services;

class UserService
{
    public function getUser(): string
    {
        return 'user';
    }
}

namespace App\Services\Tests;

use PHPUnit\Framework\Attributes\Test;

class UserServiceTest
{
    #[Test]
    public function testGetUser(): void
    {
        test()->assertEquals('user', (new \App\Services\UserService())->getUser());
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringContainsString('namespace App\Services;', $result);
        $this->assertStringContainsString('class UserService', $result);
        $this->assertStringContainsString('function getUser', $result);
        $this->assertStringNotContainsString('namespace App\Services\Tests', $result);
        $this->assertStringNotContainsString('UserServiceTest', $result);
    }

    #[Test]
    public function testStripsTestUseStatements(): void
    {
        $input = <<<'PHP'
<?php

namespace App;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use NSRosenqvist\PHPUnitInline\Attributes\Factory;
use App\SomeClass;

class MyClass
{
    public function doSomething(): void
    {
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringNotContainsString('use PHPUnit\Framework\Attributes\Test', $result);
        $this->assertStringNotContainsString('use PHPUnit\Framework\Attributes\Before', $result);
        $this->assertStringNotContainsString('use PHPUnit\Framework\Attributes\DataProvider', $result);
        $this->assertStringNotContainsString('use NSRosenqvist\PHPUnitInline\Attributes\Factory', $result);
        $this->assertStringContainsString('use App\SomeClass;', $result);
    }

    #[Test]
    public function testStripsStandaloneFunctionsWithTestAttribute(): void
    {
        $input = <<<'PHP'
<?php

namespace App\Helpers;

function formatCurrency(float $amount): string
{
    return '$' . number_format($amount, 2);
}

#[Test]
function testFormatCurrency(): void
{
    test()->assertEquals('$10.00', formatCurrency(10));
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringContainsString('function formatCurrency', $result);
        $this->assertStringNotContainsString('testFormatCurrency', $result);
        $this->assertStringNotContainsString('#[Test]', $result);
    }

    #[Test]
    public function testPreservesNonTestMethods(): void
    {
        $input = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    private function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    protected function divide(int $a, int $b): int
    {
        return intdiv($a, $b);
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringContainsString('function add', $result);
        $this->assertStringContainsString('function multiply', $result);
        $this->assertStringContainsString('function divide', $result);
    }

    #[Test]
    public function testHandlesMultipleAttributesOnMethod(): void
    {
        $input = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    #[Test]
    #[TestDox('Adding two numbers')]
    private function testAdd(): void
    {
        test()->assertEquals(5, $this->add(2, 3));
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringNotContainsString('#[Test]', $result);
        $this->assertStringNotContainsString('#[TestDox', $result);
        $this->assertStringNotContainsString('testAdd', $result);
        $this->assertStringContainsString('function add', $result);
    }

    #[Test]
    public function testCleansUpMultipleBlankLines(): void
    {
        $input = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }




    #[Test]
    private function testAdd(): void
    {
        test()->assertEquals(5, $this->add(2, 3));
    }
}
PHP;

        $result = $this->stripper->strip($input);

        // Should not have more than 2 consecutive newlines
        $this->assertDoesNotMatchRegularExpression('/\n{4,}/', $result);
    }

    #[Test]
    public function testHandlesNestedBracesInMethodBody(): void
    {
        $input = <<<'PHP'
<?php

class Calculator
{
    public function process(array $items): int
    {
        $sum = 0;
        foreach ($items as $item) {
            if ($item > 0) {
                $sum += $item;
            }
        }
        return $sum;
    }

    #[Test]
    private function testProcess(): void
    {
        $result = $this->process([1, 2, 3]);
        if ($result > 0) {
            test()->assertTrue(true);
        }
        test()->assertEquals(6, $result);
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringContainsString('function process', $result);
        $this->assertStringNotContainsString('testProcess', $result);
        // Make sure the class structure is intact
        $this->assertStringContainsString('class Calculator', $result);
        $this->assertSame(1, substr_count($result, 'class Calculator'));
    }

    #[Test]
    public function testHandlesStringsContainingBraces(): void
    {
        $input = <<<'PHP'
<?php

class Formatter
{
    public function format(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Test]
    private function testFormat(): void
    {
        $result = $this->format("World");
        test()->assertEquals("Hello, {World}!", $result);
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringContainsString('function format', $result);
        $this->assertStringContainsString('"Hello, {$name}!"', $result);
        $this->assertStringNotContainsString('testFormat', $result);
    }

    #[Test]
    public function testDoesNotRemoveTestCaseOrFixturesNamespaces(): void
    {
        $input = <<<'PHP'
<?php

namespace App\TestCase;

class BaseTestCase
{
    public function helper(): void
    {
    }
}

namespace App\Fixtures;

class UserFixture
{
    public function load(): void
    {
    }
}
PHP;

        $result = $this->stripper->strip($input);

        $this->assertStringContainsString('namespace App\TestCase', $result);
        $this->assertStringContainsString('namespace App\Fixtures', $result);
        $this->assertStringContainsString('class BaseTestCase', $result);
        $this->assertStringContainsString('class UserFixture', $result);
    }

    private function normalizeWhitespace(string $content): string
    {
        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        // Remove trailing whitespace
        $content = preg_replace('/[ \t]+$/m', '', $content) ?? $content;
        // Normalize multiple blank lines to single
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;
        // Trim
        return trim($content);
    }
}
