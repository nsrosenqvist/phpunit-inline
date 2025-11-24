# PHPUnit Lifecycle Methods

The inline tests extension fully supports PHPUnit's lifecycle attributes for both inline tests and namespace-based tests:

- `#[BeforeClass]` - Runs once before all tests in the class
- `#[Before]` - Runs before each test method
- `#[After]` - Runs after each test method
- `#[AfterClass]` - Runs once after all tests in the class

## Example: Inline Tests with Lifecycle Methods

```php
<?php

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\Test;

class UserService
{
    private static ?PDO $db = null;
    private array $cache = [];
    
    #[BeforeClass]
    public static function setUpDatabase(): void
    {
        self::$db = new PDO('sqlite::memory:');
        self::$db->exec('CREATE TABLE users (id INT, name TEXT)');
    }
    
    #[Before]
    public function clearCache(): void
    {
        $this->cache = [];
    }
    
    #[Test]
    public function itStoresUsers(): void
    {
        $stmt = self::$db->prepare('INSERT INTO users VALUES (?, ?)');
        $stmt->execute([1, 'Alice']);
        
        $this->assertTrue(true);
    }
    
    #[Test]
    public function itCachesData(): void
    {
        $this->cache['user'] = 'Bob';
        $this->assertEquals('Bob', $this->cache['user']);
    }
    
    #[After]
    public function logTestCompletion(): void
    {
        // Clean up after each test
    }
    
    #[AfterClass]
    public static function tearDownDatabase(): void
    {
        self::$db = null;
    }
}
```

## Example: Namespace-Based Tests with Lifecycle Methods

```php
<?php

namespace App\Services;

class EmailQueue
{
    public function add(string $email): void { /* ... */ }
}

namespace App\Services\Tests;

use App\Services\EmailQueue;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;

class EmailQueueTests
{
    private EmailQueue $queue;
    
    #[Before]
    public function setUpQueue(): void
    {
        $this->queue = new EmailQueue();
    }
    
    #[Test]
    public function itAddsEmailsToQueue(): void
    {
        $this->queue->add('test@example.com');
        $this->assertTrue(true);
    }
}
```

## Execution Order

Lifecycle methods execute in this order:

1. `#[BeforeClass]` (once before all tests)
2. For each test:
   - `#[Before]`
   - Test method
   - `#[After]`
3. `#[AfterClass]` (once after all tests)

Each test method gets a fresh instance, ensuring test isolation while lifecycle methods provide setup and teardown hooks.
