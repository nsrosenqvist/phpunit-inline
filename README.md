# PHPUnit Inline Tests

> **⚠️ Experimental Project**  
> This is an experimental project to explore ideas around inline testing in PHP. It is **not recommended for use** at this time. The API and implementation may change significantly as concepts are refined.

A PHPUnit runner that enables writing tests inline with your application code using PHPUnit's native `#[Test]` attribute, inspired by Rust's testing approach.

## Table of Contents

- [Features](#features)
- [Why Inline Tests?](#why-inline-tests)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Use Cases](#use-cases)
  - [1. Inline Tests Inside a Class](#1-inline-tests-inside-a-class)
  - [2. Tests in a Sub-Namespace (Rust-style)](#2-tests-in-a-sub-namespace-rust-style)
  - [3. Tests for Helper Functions](#3-tests-for-helper-functions)
- [The test() Helper Function](#the-test-helper-function)
- [Factory Methods](#factory-methods)
- [State Management](#state-management)
- [Lifecycle Methods](#lifecycle-methods)
- [Data Providers](#data-providers)
- [Mocking Support](#mocking-support)
- [PHPStan Integration](#phpstan-integration)
- [Stripping Tests for Production](#stripping-tests-for-production)
- [Best Practices](#best-practices)
- [Requirements](#requirements)
- [Contributing](#contributing)

## Features

- ✅ Write tests directly in your application classes using `#[Test]` attribute
- ✅ **Function-based tests** - Write tests as standalone functions (Rust-style)
- ✅ **Namespace-based test organization** - Group tests in `\Tests` sub-namespaces
- ✅ **Factory methods** - Use `#[Factory]` and `#[DefaultFactory]` for classes with constructor arguments
- ✅ Access private/protected class methods directly - no reflection hacks
- ✅ Full compatibility with PHPUnit's mocking and assertion features
- ✅ **Data providers** - Works with both class methods and functions
- ✅ **Lifecycle methods** - `#[Before]`, `#[After]`, `#[BeforeClass]`, `#[AfterClass]`
- ✅ **Test stripping** - Remove inline tests for production builds
- ✅ PHPStan integration to prevent false positives
- ✅ Configurable directory scanning

## Why Inline Tests?

This project is built on the idea of code colocation: when tests live alongside the code they test, writing them becomes simpler and test-driven development feels more natural.

Inspired by Rust's `#[test]` attribute, this extension allows you to:
- Keep tests close to the code they test
- Test private/protected methods without reflection hacks
- Write tests as simple functions or class methods
- Organize tests using namespaces (like Rust's `mod tests`)
- Maintain a clear separation between unit tests and integration tests
- Reduce context switching when developing

## Installation

```bash
composer require --dev nsrosenqvist/phpunit-inline
```

## Quick Start

Here's a minimal example to get you started:

```php
<?php

declare(strict_types=1);

namespace App;

use PHPUnit\Framework\Attributes\Test;

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
```

Run your tests with the provided CLI wrapper:

```bash
# Scan specific directories for inline tests
./vendor/bin/phpunit-inline --scan-directories=src

# Scan multiple directories
./vendor/bin/phpunit-inline --scan-directories=src,app

# Pass any PHPUnit options
./vendor/bin/phpunit-inline --scan-directories=src --testdox --colors=always
```

### Configuration in phpunit.xml

Instead of passing `--scan-directories` every time, you can configure the directories in your `phpunit.xml`:

**Option 1: Using `<inlineTests>` element (recommended)**

```xml
<phpunit bootstrap="vendor/autoload.php">
    <inlineTests>
        <directory>src</directory>
        <directory>app</directory>
    </inlineTests>
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**Option 2: Using environment variable**

```xml
<phpunit bootstrap="vendor/autoload.php">
    <php>
        <env name="PHPUNIT_INLINE_SCAN_DIRECTORIES" value="src,app"/>
    </php>
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Then simply run:

```bash
./vendor/bin/phpunit-inline
```

### Backwards Compatibility with Existing Tests

The CLI wrapper is **fully backwards compatible** with existing PHPUnit test suites. If you have a `phpunit.xml` configuration with testsuites defined, those tests will run alongside your inline tests:

```bash
# Runs both phpunit.xml testsuites AND inline tests from src/
./vendor/bin/phpunit-inline --scan-directories=src

# Works as a drop-in replacement for vendor/bin/phpunit
./vendor/bin/phpunit-inline
```

This means you can adopt inline tests incrementally without abandoning your existing test infrastructure. Traditional tests in `tests/` and inline tests in `src/` will all run together in a single test run.

### Custom Autoloader Setup

If you use **helper classes in `\Tests` sub-namespaces** (such as state classes for type-hinting), you need to register the custom autoloader. This is because classes like `App\Services\SomeService\Tests\TestState` are defined in the same file as `App\Services\SomeService`, which doesn't follow PSR-4 conventions.

**Option 1: Use the provided bootstrap file**

```xml
<phpunit bootstrap="vendor/nsrosenqvist/phpunit-inline/src/bootstrap.php">
    <!-- ... -->
</phpunit>
```

**Option 2: Register manually in your own bootstrap file**

```php
<?php
// tests/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

// Register inline test autoloader for helper classes in \Tests namespaces
use NSRosenqvist\PHPUnitInline\Autoloader\InlineTestAutoloader;

$autoloader = InlineTestAutoloader::fromComposerJson(__DIR__ . '/../composer.json');
$autoloader->register();
```

Then reference it in your `phpunit.xml`:

```xml
<phpunit bootstrap="tests/bootstrap.php">
    <!-- ... -->
</phpunit>
```

> **Note:** If you only use inline tests inside classes (pattern #1) or function-based tests (patterns #2 and #3), you don't need this autoloader setup. It's only required when you define helper classes (like state classes) in `\Tests` sub-namespaces.

## Use Cases

This extension supports three main patterns for organizing inline tests. Choose what best fits your needs.

### 1. Inline Tests Inside a Class

**Best for**: Testing private/protected methods of a class without reflection.

Write tests directly inside the class they're testing. The `$this` variable refers to your class instance, giving you direct access to private and protected methods.

```php
<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    private function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    // ==================== Inline Tests ====================

    #[Test]
    #[TestDox('Adding two numbers returns their sum')]
    private function testAdd(): void
    {
        $result = $this->add(2, 3);
        
        // test() provides PHPUnit assertions
        test()->assertEquals(5, $result);
    }

    #[Test]
    #[TestDox('Can test private multiply method directly')]
    private function testMultiplyPrivateMethod(): void
    {
        $result = $this->multiply(4, 5);
        test()->assertEquals(20, $result);
    }

    #[Test]
    private function testWithMocking(): void
    {
        $mock = test()->createMock(\stdClass::class);
        test()->assertInstanceOf(\stdClass::class, $mock);
    }
}
```

**Output:**
```
Calculator (App\Services\Calculator)
 ✔ Adding two numbers returns their sum
 ✔ Can test private multiply method directly
 ✔ With mocking
```

### 2. Tests in a Sub-Namespace (Rust-style)

**Best for**: Keeping tests separate from production code but in the same file, similar to Rust's `mod tests` pattern.

Place your tests in a `\Tests` sub-namespace within the same file. This is the cleanest separation while still co-locating tests with the code.

```php
<?php

declare(strict_types=1);

namespace App\Math;

function add(int $a, int $b): int
{
    return $a + $b;
}

function multiply(int $a, int $b): int
{
    return $a * $b;
}

// ==================== Tests ====================

namespace App\Math\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use function App\Math\add;
use function App\Math\multiply;

#[Test]
function testAdd(): void
{
    test()->assertEquals(5, add(2, 3));
}

#[Test]
function testMultiply(): void
{
    test()->assertEquals(20, multiply(4, 5));
}

#[Test]
#[DataProvider('additionCases')]
function testAddWithDataProvider(int $a, int $b, int $expected): void
{
    test()->assertEquals($expected, add($a, $b));
}

function additionCases(): array
{
    return [
        'positive numbers' => [2, 3, 5],
        'with zero' => [5, 0, 5],
        'negative numbers' => [-2, -3, -5],
    ];
}
```

### 3. Tests for Helper Functions

**Best for**: Testing functions in helper files where creating a namespace feels like overkill.

When you have a file with utility functions (like `helpers.php`), you can write tests in the same namespace, right next to the functions. The generated test class name is derived from the filename (e.g., `helpers.php` → `HelpersTest`).

```php
<?php
// src/helpers.php

declare(strict_types=1);

namespace App;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Formats a given amount into a currency string.
 *
 * @param float $amount The amount to format.
 * @param string $currency The currency code (e.g., 'USD', 'EUR', 'GBP').
 * @return string The formatted currency string.
 */
function formatCurrency(float $amount, string $currency = 'USD'): string
{
    return match ($currency) {
        'USD' => '$' . number_format($amount, 2),
        'EUR' => '€' . number_format($amount, 2, ',', '.'),
        'GBP' => '£' . number_format($amount, 2),
        default => number_format($amount, 2) . ' ' . $currency,
    };
}

#[Test]
function testFormatCurrencyUSD(): void
{
    test()->assertEquals('$1,234.56', formatCurrency(1234.56));
    test()->assertEquals('$0.00', formatCurrency(0));
}

#[Test]
function testFormatCurrencyEUR(): void
{
    test()->assertEquals('€1.234,56', formatCurrency(1234.56, 'EUR'));
}

/**
 * Convert a string into a URL-friendly "slug".
 *
 * @param string $text The input string to be slugified.
 * @return string The slugified version of the input string.
 */
function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    return preg_replace('~-+~', '-', $text);
}

#[Test]
#[DataProvider('slugifyProvider')]
function testSlugify(string $input, string $expected): void
{
    test()->assertEquals($expected, slugify($input));
}

function slugifyProvider(): array
{
    return [
        ['Hello World', 'hello-world'],
        ['PHP is GREAT!!!', 'php-is-great'],
        ['Multiple   Spaces', 'multiple-spaces'],
    ];
}
```

**Output:**
```
HelpersTest (App\HelpersTest)
 ✔ Format currency USD
 ✔ Format currency EUR
 ✔ Slugify with data set "Hello World"
 ✔ Slugify with data set "PHP is GREAT!!!"
 ✔ Slugify with data set "Multiple   Spaces"
```

## The test() Helper Function

The `test()` function is a global helper that provides access to PHPUnit assertions from within inline tests. This creates a clear separation of concerns:

| Context | Access |
|---------|--------|
| `$this` | Your class instance (private/protected methods and properties) |
| `test()` | PHPUnit TestCase (assertions, mocking, expectations) |

```php
#[Test]
private function testExample(): void
{
    // Access class methods via $this
    $result = $this->privateMethod();
    
    // Access PHPUnit via test()
    test()->assertEquals(42, $result);
    test()->assertTrue($result > 0);
    test()->assertCount(3, $this->items);
    
    // Mocking
    $mock = test()->createMock(SomeInterface::class);
    $mock->expects(test()->once())->method('doSomething');
    
    // Exception testing
    test()->expectException(\InvalidArgumentException::class);
    test()->expectExceptionMessage('Invalid input');
}
```

## Factory Methods

For classes that require constructor arguments, use factory methods to create instances for testing.

### Basic Factory

```php
<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\Attributes\Test;
use NSRosenqvist\PHPUnitInline\Attributes\DefaultFactory;

class OrderProcessor
{
    public function __construct(
        private PaymentGateway $paymentGateway,
        private InventoryService $inventory,
    ) {}

    public function process(Order $order): bool
    {
        // ... implementation
    }

    // ==================== Test Support ====================

    #[DefaultFactory]
    private static function createForTesting(): self
    {
        return new self(
            new MockPaymentGateway(),
            new MockInventoryService(),
        );
    }

    // ==================== Tests ====================

    #[Test]
    private function testProcessOrder(): void
    {
        // $this is an instance created by createForTesting()
        $order = new Order(100.00, ['item1', 'item2']);
        
        $result = $this->process($order);
        
        test()->assertTrue($result);
    }
}
```

### Multiple Factories

Use `#[Factory('name')]` to specify which factory to use, and `#[DefaultFactory]` to set the default:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\Attributes\Test;
use NSRosenqvist\PHPUnitInline\Attributes\Factory;
use NSRosenqvist\PHPUnitInline\Attributes\DefaultFactory;

class PaymentService
{
    public function __construct(
        private PaymentGateway $gateway,
        private Logger $logger,
    ) {}

    public function charge(float $amount): bool
    {
        $this->logger->info("Charging {$amount}");
        return $this->gateway->charge($amount);
    }

    // ==================== Factories ====================

    #[DefaultFactory]
    private static function createWithMocks(): self
    {
        return new self(
            test()->createStub(PaymentGateway::class),
            new NullLogger(),
        );
    }

    private static function createWithFailingGateway(): self
    {
        $gateway = test()->createStub(PaymentGateway::class);
        $gateway->method('charge')->willReturn(false);
        
        return new self($gateway, new NullLogger());
    }

    // ==================== Tests ====================

    #[Test]
    private function testChargeLogsAmount(): void
    {
        // Uses default factory (createWithMocks)
        $this->charge(99.99);
        test()->assertTrue(true); // Verify no exceptions
    }

    #[Test]
    #[Factory('createWithFailingGateway')]
    private function testChargeReturnsFalseOnGatewayFailure(): void
    {
        // Uses the failing gateway factory
        $result = $this->charge(50.00);
        test()->assertFalse($result);
    }
}
```

## State Management

For tests that need shared state across all test methods (similar to PHPUnit's `setUpBeforeClass()`), use the `#[State]` attribute and `state()` helper function.

### Basic Usage

The `#[State]` attribute marks a function or static method as a state initializer. The return value becomes the shared state accessible via `state()`.

**Function-based example:**

```php
<?php

declare(strict_types=1);

namespace App\Database\Tests;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPUnit\Framework\Attributes\Test;

/**
 * State class for type-hinting.
 */
class DatabaseState
{
    public \PDO $connection;
    /** @var array<string, mixed> */
    public array $fixtures = [];
}

#[State]
function initState(): DatabaseState
{
    $state = new DatabaseState();
    $state->connection = new \PDO('sqlite::memory:');
    $state->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    return $state;
}

#[Test]
function testInsertUser(): void
{
    state()->connection->exec("INSERT INTO users (name) VALUES ('John')");
    
    $count = state()->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    test()->assertEquals(1, (int) $count);
}

#[Test]
function testCanSeeInsertedUser(): void
{
    // State is shared - this test can see data from previous test
    $count = state()->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    test()->assertGreaterThanOrEqual(1, (int) $count);
}
```

**Class-based example:**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPUnit\Framework\Attributes\Test;

/**
 * @phpstan-type TestState array{multiplier: int, results: array<int>}
 */
class Calculator
{
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    /**
     * State initializer must be static for class-based tests.
     *
     * @return TestState
     */
    #[State]
    private static function initTestState(): array
    {
        return [
            'multiplier' => 10,
            'results' => [],
        ];
    }

    #[Test]
    private function testWithState(): void
    {
        /** @var TestState $s */
        $s = state();
        $result = $this->multiply(5, $s['multiplier']);
        test()->assertEquals(50, $result);
    }

    #[Test]
    private function testCanModifyState(): void
    {
        /** @var TestState $s */
        $s = state();
        $s['results'][] = $this->multiply(2, $s['multiplier']);
        state($s); // Update the state

        test()->assertEquals([20], $s['results']);
    }
}
```

### State Behavior

- **Initialized once**: The state initializer runs once before all tests (like `setUpBeforeClass()`)
- **Shared across tests**: All tests in the same test case share the same state
- **Mutable**: Tests can modify state by calling `state($newValue)`
- **NOT reset between tests**: Changes persist across individual test methods

### Type Hinting

For better IDE support, create a dedicated state class with a return type on your initializer:

```php
class TestState
{
    public \PDO $db;
    public UserService $service;
    /** @var array<User> */
    public array $users = [];
}

#[State]
function initState(): TestState
{
    $state = new TestState();
    $state->db = new \PDO('sqlite::memory:');
    $state->service = new UserService($state->db);
    return $state;
}

#[Test]
function testSomething(): void
{
    $s = state();
    $s->service->createUser('John');
    // ...
}
```

The included PHPStan extension automatically infers the return type of `state()` based on the return type of your `#[State]` initializer function/method. This means PHPStan will understand that `$s` is of type `TestState` in the example above.

> **Note:** For IDE autocompletion, you may still need `@var` annotations since IDEs may not load the PHPStan extension.

## Lifecycle Methods

All PHPUnit lifecycle attributes work with inline tests:

```php
<?php

declare(strict_types=1);

namespace App\Database\Tests;

use NSRosenqvist\PHPUnitInline\Attributes\State;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\AfterClass;

class TestState
{
    public \PDO $connection;
}

#[State]
function initState(): TestState
{
    $state = new TestState();
    $state->connection = new \PDO('sqlite::memory:');
    $state->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    return $state;
}

#[BeforeClass]
function setUpDatabase(): void
{
    // Additional setup that runs once before all tests
}

#[Before]
function beginTransaction(): void
{
    state()->connection->beginTransaction();
}

#[Test]
function testInsertUser(): void
{
    state()->connection->exec("INSERT INTO users (name) VALUES ('John')");
    
    $count = state()->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    test()->assertEquals(1, $count);
}

#[After]
function rollbackTransaction(): void
{
    state()->connection->rollBack();
}

#[AfterClass]
function tearDownDatabase(): void
{
    // Cleanup that runs once after all tests
}
```

## Data Providers

Data providers work with both class methods and functions:

**Class-based:**

```php
class StringUtilsTest
{
    #[Test]
    #[DataProvider('uppercaseProvider')]
    public function testUppercase(string $input, string $expected): void
    {
        test()->assertEquals($expected, strtoupper($input));
    }

    public static function uppercaseProvider(): array
    {
        return [
            'lowercase' => ['hello', 'HELLO'],
            'mixed case' => ['HeLLo', 'HELLO'],
            'already uppercase' => ['HELLO', 'HELLO'],
        ];
    }
}
```

**Function-based:**

```php
namespace App\Utils\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

#[Test]
#[DataProvider('trimProvider')]
function testTrim(string $input, string $expected): void
{
    test()->assertEquals($expected, trim($input));
}

function trimProvider(): array
{
    return [
        ['  hello  ', 'hello'],
        ["\t\nworld\t\n", 'world'],
        ['no-spaces', 'no-spaces'],
    ];
}
```

## Mocking Support

Full PHPUnit mocking is available through `test()`:

```php
#[Test]
private function testWithMocks(): void
{
    // Create a mock with expectations
    $userRepository = test()->createMock(UserRepository::class);
    $userRepository->expects(test()->once())
        ->method('find')
        ->with(42)
        ->willReturn(new User(42, 'John'));

    // Create a stub (simpler, no expectations)
    $logger = test()->createStub(LoggerInterface::class);
    $logger->method('info')->willReturn(null);

    // Use in your test
    $service = new UserService($userRepository, $logger);
    $user = $service->getUser(42);

    test()->assertEquals('John', $user->getName());
}
```

## PHPStan Integration

The extension includes PHPStan integration that is automatically registered via `phpstan/extension-installer`. This provides:

**False positive prevention:**
- `#[Test]` methods being reported as "unused"
- `#[Before]`, `#[After]`, `#[BeforeClass]`, `#[AfterClass]` methods being reported as "unused"
- `#[Factory]` and `#[DefaultFactory]` methods being reported as "unused"
- `#[DataProvider]` methods being reported as "unused"
- Protected TestCase methods accessed via the `test()` helper

**Type inference:**
- `state()` return type is automatically inferred from the `#[State]` initializer's return type

No additional configuration is needed if you use `phpstan/extension-installer`.

## Stripping Tests for Production

Inline tests add overhead to your source files. For production deployments, you can strip all test code using the provided command. Unused methods shouldn't affect your application's performance, as long as you use opcache, so this is primarily for slimming down the production build.

> **⚠️ Warning**: This command **permanently modifies files**. Only run this during container image builds or deployment pipelines. **Never run this on your development environment.**

### Usage

```bash
# Strip tests from src/ directory
vendor/bin/phpunit-inline-strip src/

# Strip tests from multiple directories
vendor/bin/phpunit-inline-strip src/ app/ lib/

# Preview what would be removed (dry run)
vendor/bin/phpunit-inline-strip --dry-run src/

# Verbose output
vendor/bin/phpunit-inline-strip -v src/
```

### What Gets Removed

The strip command removes:
- Methods with `#[Test]` attribute
- Methods with `#[Before]`, `#[After]`, `#[BeforeClass]`, `#[AfterClass]` attributes
- Methods with `#[DataProvider]` attribute (when only used by tests)
- Methods with `#[Factory]` and `#[DefaultFactory]` attributes
- Methods and functions with `#[State]` attribute
- Functions with `#[Test]` attribute
- Entire `\Tests` sub-namespaces
- Test-related `use` statements

### Example: Docker Build

```dockerfile
FROM php:8.2-fpm

# Copy source files
COPY . /app

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Strip inline tests from production build
RUN vendor/bin/phpunit-inline-strip src/

# Continue with your build...
```

### Example: CI/CD Pipeline

```yaml
# GitHub Actions example
deploy:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    
    - name: Install dependencies
      run: composer install --no-dev
    
    - name: Strip inline tests
      run: vendor/bin/phpunit-inline-strip src/
    
    - name: Deploy
      run: ./deploy.sh
```

## Best Practices

### Choosing the Right Pattern

| Pattern | Use When |
|---------|----------|
| **Inline in class** | Testing private/protected methods of a specific class |
| **Sub-namespace** | You want Rust-style `mod tests` separation |
| **Same-namespace functions** | Testing utility functions in helper files |
| **Traditional tests/** | Integration tests, complex test scenarios |

### Recommendations

1. **Keep it simple**: Don't mix patterns unnecessarily. Pick one pattern per file.

2. **Test private methods sparingly**: Just because you *can* test private methods doesn't mean you always *should*. Focus on testing behavior through public APIs when possible.

3. **Use factories for complex setup**: If your class requires constructor arguments, use `#[Factory]` methods rather than complex test setup.

4. **Separate integration tests**: Keep integration tests in traditional `tests/` directory. Inline tests are best for unit tests.

5. **Don't overdo it**: Not every class needs inline tests. Use judgment about where they add value.

## Requirements

- PHP 8.2 or higher
- PHPUnit 11.0 or 12.0

## Contributing

Contributions are welcome! Please ensure:

1. **Code quality**: All code follows PSR-12 with `declare(strict_types=1)`
2. **Run checks**: `composer check` - runs tests, formatting, and static analysis
3. **Auto-fix formatting**: `composer format` (if needed)

The `composer check` command verifies everything before submitting a PR.

## License

MIT License - see LICENSE file for details
