# Data Provider Example

This example demonstrates how to use `#[DataProvider]` with inline tests.

```php
<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    // Data provider method
    public static function additionProvider(): array
    {
        return [
            'positive numbers' => [2, 3, 5],
            'negative numbers' => [-2, -3, -5],
            'with zero' => [0, 5, 5],
        ];
    }

    // Inline test using data provider
    #[Test]
    #[DataProvider('additionProvider')]
    private function testAddition(int $a, int $b, int $expected): void
    {
        $result = $this->add($a, $b);
        $this->assertEquals($expected, $result);
    }
}
```

## Key Points

- Data provider methods can be `static` or instance methods
- **Data providers can be private, protected, or public** (reflection with `setAccessible(true)` is used)
- Data provider returns an array where each element is an array of parameters
- Named data sets (string keys) will appear in test output: `with data set "positive numbers"`
- Numbered data sets (numeric keys) will appear as: `with data set #0`
- You can mix regular tests and data provider tests in the same class

## Data Provider Return Format

```php
public static function myDataProvider(): array
{
    return [
        'named set' => [param1, param2, param3],  // Named
        [param1, param2, param3],                  // Numbered (index 0)
        [param1, param2, param3],                  // Numbered (index 1)
    ];
}
```

Each inner array is passed as parameters to the test method.

## Private Data Providers

Data providers can be private, just like test methods:

```php
class MyClass
{
    // Private static data provider
    private static function myPrivateProvider(): array
    {
        return [
            'case 1' => [1, 2, 3],
            'case 2' => [4, 5, 9],
        ];
    }

    #[Test]
    #[DataProvider('myPrivateProvider')]
    private function testWithPrivateProvider(int $a, int $b, int $expected): void
    {
        $this->assertEquals($expected, $a + $b);
    }
}
```

This keeps your test helpers encapsulated within the class.
