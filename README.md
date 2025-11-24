# PHPUnit Inline Tests

A PHPUnit extension that enables writing tests inline with your application code using PHPUnit's native `#[Test]` attribute, inspired by Rust's testing approach.

## Features

- ✅ Write tests directly in your application classes using `#[Test]` attribute
- ✅ Access both private/protected class methods AND PHPUnit assertions
- ✅ Full compatibility with PHPUnit's mocking and assertion features
- ✅ PHPStan integration to prevent false positives
- ✅ Configurable directory scanning
- ✅ PSR-12 compliant with strict types
- ✅ No custom attributes needed - uses PHPUnit's built-in `#[Test]` attribute

## Why Inline Tests?

Inspired by Rust's `#[test]` attribute, this extension allows you to:
- Keep tests close to the code they test
- Test private/protected methods without reflection hacks
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

## How It Works

The extension works through several components:

1. **Scanner**: Scans configured directories for classes with `#[Test]` attributes
2. **Test Wrapper**: Creates dynamic TestCase instances that wrap your application classes
3. **Test Proxy**: Routes method calls to either:
   - Your class instance (for testing private/protected methods)
   - PHPUnit's TestCase (for assertions, mocking, etc.)
4. **PHPStan Integration**: Understands the dual context and prevents false positives

### Behind the Scenes

When you write:
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
2. Creates an `InlineTestCase` wrapper
3. Provides a `TestProxy` that makes `$this->multiply()` route to your class
4. Makes `$this->assertEquals()` route to PHPUnit's assertion methods
5. Executes the test with full PHPUnit features

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

## Best Practices

1. **Use for unit tests only**: Inline tests are perfect for testing individual class methods
2. **Keep integration tests separate**: Use traditional test files for integration tests
3. **Test private implementation details**: This is where inline tests shine
4. **Don't overuse**: Not every class needs inline tests - use judgment

## Requirements

- PHP 8.2 or higher
- PHPUnit 11.0 or 12.0

## Contributing

Contributions are welcome! Please ensure:
- All code follows PSR-12
- Every file has `declare(strict_types=1)` 
- Tests pass with PHPUnit
- Code passes PHPStan analysis at max level

## License

MIT License - see LICENSE file for details
