# PHPUnit Inline Tests

A PHPUnit extension that enables writing tests inline with your application code using PHPUnit's native `#[Test]` attribute, inspired by Rust's testing approach.

## Features

- ✅ Write tests directly in your application classes using `#[Test]` attribute
- ✅ **Function-based tests** - Write tests as standalone functions (Rust-style)
- ✅ **Namespace-based test organization** - Group tests in `Tests` namespaces
- ✅ Access both private/protected class methods AND PHPUnit assertions
- ✅ Full compatibility with PHPUnit's mocking and assertion features
- ✅ **Data providers** - Works with both class methods and functions
- ✅ **Lifecycle methods** - `#[Before]`, `#[After]`, `#[BeforeClass]`, `#[AfterClass]`
- ✅ PHPStan integration to prevent false positives
- ✅ Configurable directory scanning
- ✅ PSR-12 compliant with strict types
- ✅ No custom attributes needed - uses PHPUnit's built-in attributes

## Why Inline Tests?

Inspired by Rust's `#[test]` attribute, this extension allows you to:
- Keep tests close to the code they test
- Test private/protected methods without reflection hacks
- Write tests as simple functions or class methods
- Organize tests using namespaces (like Rust's `mod tests`)
- Maintain a clear separation between unit tests and integration tests
- Reduce context switching when developing

## Installation

```bash
composer require --dev phpunit/inline-tests
```

## Configuration

### Step 1: Configure PHPUnit Bootstrap

For **namespace-based tests** (Rust-style `mod tests` pattern), you need to register the custom autoloader.

Update your `phpunit.xml` bootstrap:

```xml
<phpunit bootstrap="vendor/phpunit/inline-tests/src/bootstrap.php">
    <!-- ... -->
</phpunit>
```

If you already have a custom bootstrap file, add this at the top:

```php
<?php
// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Register inline test autoloader
use PHPUnit\InlineTests\Autoloader\InlineTestAutoloader;

$autoloader = InlineTestAutoloader::fromComposerJson(__DIR__ . '/composer.json');
$autoloader->register();

// ... rest of your bootstrap code
```

### Step 2: Configure the Extension

Add the extension to your `phpunit.xml`:

```xml
<phpunit>
    <!-- ... other configuration ... -->
    
    <extensions>
        <bootstrap class="PHPUnit\InlineTests\Extension\InlineTestExtension">
            <parameter name="scanDirectories" value="src,app"/>
        </bootstrap>
    </extensions>
    
    <testsuites>
        <testsuite name="Inline Tests">
            <file>vendor/phpunit/inline-tests/tests/InlineTestsSuite.php</file>
        </testsuite>
        
        <!-- Your regular test suites -->
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Step 3: Configure PHPStan (Optional)

The extension includes PHPStan integration. It's automatically registered via `phpstan/extension-installer`.

## Usage

### Class-Based Tests

Write tests as methods within your application classes:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\Attributes\Test;

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

    protected function divide(int $a, int $b): int
    {
        if ($b === 0) {
            throw new \InvalidArgumentException('Cannot divide by zero');
        }
        return intdiv($a, $b);
    }

    // ==================== Inline Tests ====================

    #[Test]
    private function testAdd(): void
    {
        // $this has access to both the class methods and PHPUnit assertions
        $result = $this->add(2, 3);
        $this->assertEquals(5, $result);
    }

    #[Test]
    private function testMultiplyPrivateMethod(): void
    {
        // Can access private methods directly - no reflection needed!
        $result = $this->multiply(4, 5);
        $this->assertEquals(20, $result);
    }

    #[Test]
    protected function testDivideByZeroThrowsException(): void
    {
        // Full PHPUnit feature support including exception testing
        $this->expectException(\InvalidArgumentException::class);
        $this->divide(10, 0);
    }

    #[Test]
    private function testCanUseMocking(): void
    {
        // Even mocking works!
        $mock = $this->createMock(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $mock);
    }
}
```

### Function-Based Tests

Write tests as standalone functions - perfect for testing pure functions or utility code:

```php
<?php

declare(strict_types=1);

namespace App\Math;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

// Production functions
function add(int $a, int $b): int
{
    return $a + $b;
}

function multiply(int $a, int $b): int
{
    return $a * $b;
}

// Tests namespace - Rust-style mod tests pattern
namespace App\Math\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use function App\Math\add;
use function App\Math\multiply;

#[Test]
function testAdd(): void
{
    $result = add(2, 3);
    $this->assertEquals(5, $result); // $this has access to PHPUnit assertions!
}

#[Test]
function testMultiply(): void
{
    $result = multiply(4, 5);
    $this->assertEquals(20, $result);
}

#[Test]
#[DataProvider('additionDataProvider')]
function testAddWithDataProvider(int $a, int $b, int $expected): void
{
    $result = add($a, $b);
    $this->assertEquals($expected, $result);
}

function additionDataProvider(): array
{
    return [
        [1, 2, 3],
        [5, 5, 10],
        [10, -5, 5],
    ];
}
```

### Namespace-Based Test Organization

Organize tests in a `Tests` namespace within your module (Rust-style):

```php
<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\Attributes\Test;

class UserService
{
    public function createUser(string $email): User
    {
        // ... implementation
    }
}

// Tests in a sub-namespace
namespace App\Services\Tests;

use App\Services\User;
use App\Services\UserService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;

class UserServiceTest
{
    private UserService $service;

    #[Before]
    public function createService(): void
    {
        $this->service = new UserService();
    }

    #[Test]
    public function testCreateUser(): void
    {
        $user = $this->service->createUser('test@example.com');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test@example.com', $user->email);
    }
}
```

## How It Works

The extension works through several components:

1. **Scanner**: Scans configured directories for classes and functions with `#[Test]` attributes
2. **Autoloader**: Custom autoloader for namespace-based tests that don't follow PSR-4
3. **Test Generator**: Creates dynamic TestCase instances that wrap your classes or functions
4. **Context Binding**: Routes method calls appropriately:
   - Class methods → Your class instance (for testing private/protected methods)
   - Function bodies → Bound with TestCase context (for assertions)
   - PHPUnit assertions → TestCase methods (assertEquals, assertInstanceOf, etc.)
5. **PHPStan Integration**: Understands the dual context and prevents false positives

### Behind the Scenes

**For class-based tests:**
```php
#[Test]
private function testMultiply(): void
{
    $result = $this->multiply(4, 5);  // Calls private method
    $this->assertEquals(20, $result);  // Calls PHPUnit assertion
}
```

The extension:
1. Discovers this method during test scanning
2. Creates a dynamic `TestCase` wrapper class
3. Routes `$this->multiply()` to your class instance
4. Routes `$this->assertEquals()` to PHPUnit's assertion methods
5. Executes with full PHPUnit features

**For function-based tests:**
```php
#[Test]
function testAdd(): void
{
    $result = add(2, 3);
    $this->assertEquals(5, $result);  // $this magically works in functions!
}
```

The extension:
1. Discovers functions with `#[Test]` attribute
2. Groups functions by namespace
3. Creates a dynamic `TestCase` class for each namespace
4. Binds each function body to the TestCase context using closures
5. Makes `$this` available inside functions for PHPUnit assertions

## Comparison with Traditional Tests

### Traditional Approach
```php
// src/Calculator.php
class Calculator {
    private function multiply(int $a, int $b): int { 
        return $a * $b; 
    }
}

// tests/CalculatorTest.php
class CalculatorTest extends TestCase {
    public function testMultiply(): void {
        $calc = new Calculator();
        $reflection = new ReflectionMethod($calc, 'multiply');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($calc, 4, 5);
        $this->assertEquals(20, $result);
    }
}
```

### With Inline Tests
```php
// src/Calculator.php
class Calculator {
    private function multiply(int $a, int $b): int { 
        return $a * $b; 
    }
    
    #[Test]
    private function testMultiply(): void {
        $this->assertEquals(20, $this->multiply(4, 5));
    }
}
```

## Mocking Support

**Yes!** Full PHPUnit mocking support works with inline tests. You can use:
- `createMock()` - Full mock objects with expectations
- `createStub()` - Simple stubs with return values
- `createPartialMock()` - Partial mocks
- All PHPUnit mock configuration methods

### Example with Mocking

```php
final class OrderProcessor
{
    public function __construct(
        private PaymentGateway $paymentGateway,
        private EmailService $emailService
    ) {}

    public function processOrder(Order $order): bool
    {
        $paymentResult = $this->paymentGateway->charge($order->getAmount());
        
        if ($paymentResult) {
            $this->emailService->sendConfirmation($order->getCustomerEmail());
        }
        
        return $paymentResult;
    }

    #[Test]
    private function testProcessOrderWithMocks(): void
    {
        // Create mocks with expectations
        $paymentGateway = $this->createMock(PaymentGateway::class);
        $paymentGateway->expects($this->once())
            ->method('charge')
            ->with(100.00)
            ->willReturn(true);

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('sendConfirmation');

        // Test with mocked dependencies
        $processor = new OrderProcessor($paymentGateway, $emailService);
        $order = new Order(100.00, 'test@example.com');
        
        $result = $processor->processOrder($order);
        
        $this->assertTrue($result);
    }

    #[Test]
    private function testWithStubs(): void
    {
        // Stubs are simpler - no expectations
        $paymentStub = $this->createStub(PaymentGateway::class);
        $paymentStub->method('charge')->willReturn(true);
        
        $emailStub = $this->createStub(EmailService::class);
        
        $processor = new OrderProcessor($paymentStub, $emailStub);
        // ... rest of test
    }
}
```

See `examples/OrderProcessor.php` for a complete example.

## Lifecycle Methods and Data Providers

### Lifecycle Methods

All PHPUnit lifecycle attributes work with both class-based and function-based tests:

```php
namespace App\Database\Tests;

use PHPUnit\Framework\Attributes\{Test, Before, After, BeforeClass, AfterClass};

class DatabaseTest
{
    private static $connection;
    private $transaction;

    #[BeforeClass]
    public static function setupDatabase(): void
    {
        self::$connection = new DatabaseConnection();
    }

    #[Before]
    public function startTransaction(): void
    {
        $this->transaction = self::$connection->beginTransaction();
    }

    #[Test]
    public function testInsert(): void
    {
        // Test runs within transaction
    }

    #[After]
    public function rollbackTransaction(): void
    {
        $this->transaction->rollback();
    }

    #[AfterClass]
    public static function closeDatabase(): void
    {
        self::$connection->close();
    }
}
```

### Data Providers

Data providers work with both class methods and functions:

**Class-based:**
```php
class MathTest
{
    #[Test]
    #[DataProvider('additionProvider')]
    public function testAddition(int $a, int $b, int $expected): void
    {
        $this->assertEquals($expected, $a + $b);
    }

    public static function additionProvider(): array
    {
        return [
            [1, 1, 2],
            [2, 3, 5],
            [10, 5, 15],
        ];
    }
}
```

**Function-based:**
```php
namespace App\Utils\Tests;

use PHPUnit\Framework\Attributes\{Test, DataProvider};

#[Test]
#[DataProvider('stringCases')]
function testStringManipulation(string $input, string $expected): void
{
    $result = manipulate($input);
    $this->assertEquals($expected, $result);
}

function stringCases(): array
{
    return [
        ['hello', 'HELLO'],
        ['world', 'WORLD'],
    ];
}
```

## Best Practices

1. **Choose the right style**:
   - **Class-based inline tests**: For testing private/protected methods of a specific class
   - **Function-based tests**: For testing pure functions and utilities
   - **Namespace-based organization**: For grouping related tests together (Rust-style)
   - **Traditional test files**: For integration tests and complex test scenarios

2. **Use for unit tests**: Inline tests are perfect for testing individual units of code
3. **Keep integration tests separate**: Use traditional test files in `tests/` directory
4. **Test private implementation details**: This is where inline tests shine - no reflection needed!
5. **Leverage lifecycle methods**: Use `#[Before]`, `#[After]` etc. for setup/teardown
6. **Use data providers**: Avoid repetitive test code with `#[DataProvider]`
7. **Don't overuse**: Not every class needs inline tests - use judgment

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
