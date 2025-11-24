# Development Guide

## Project Structure

```
phpunit-ext/
├── src/
│   ├── Extension/
│   │   └── InlineTestExtension.php      # Main PHPUnit extension
│   ├── Scanner/
│   │   ├── InlineTestScanner.php        # Discovers #[Test] methods
│   │   └── InlineTestClass.php          # Represents a class with tests
│   ├── TestCase/
│   │   ├── InlineTestCase.php           # Wrapper TestCase
│   │   ├── TestProxy.php                # Proxy for $this context
│   │   ├── InlineTestSuiteBuilder.php   # Builds test suites
│   │   └── InlineTestLoader.php         # Static loader helper
│   └── PHPStan/
│       ├── InlineTestMethodReflectionExtension.php
│       ├── InlineTestTypeExtension.php
│       └── InlineTestUnusedMethodRule.php
├── tests/
│   ├── Fixtures/                         # Test fixtures
│   ├── Unit/                             # Unit tests
│   ├── Integration/                      # Integration tests
│   └── InlineTestsSuite.php              # Suite loader
├── examples/
│   └── UserService.php                   # Example usage
└── ...

```

## Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run with detailed output
vendor/bin/phpunit --testdox

# Run specific test suite
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration

# Run with coverage (requires xdebug)
vendor/bin/phpunit --coverage-html coverage
```

## Running PHPStan

```bash
# Analyze code
vendor/bin/phpstan analyse

# With specific level
vendor/bin/phpstan analyse --level=max
```

## Code Standards

All code must:
- Follow PSR-12 coding standards
- Include `declare(strict_types=1)` at the top
- Have proper type hints
- Include PHPDoc blocks for complex methods
- Pass PHPStan at max level

## How It Works

### 1. Test Discovery

The `InlineTestScanner` scans configured directories for PHP files:
```php
$scanner = new InlineTestScanner(['src', 'app']);
$testClasses = $scanner->scan();
```

It finds all classes with methods marked with `#[Test]` attribute.

### 2. Test Execution

For each discovered test method:
1. An `InlineTestCase` is created
2. The application class instance is created
3. A `TestProxy` is created that wraps both:
   - The application class instance (for testing private methods)
   - The PHPUnit TestCase (for assertions)

### 3. The Magic: TestProxy

The `TestProxy` uses `__call()` magic method to route calls:

```php
$this->add(2, 3)          → Routes to application class method
$this->assertEquals(5, $result) → Routes to PHPUnit assertion
```

It also uses `eval()` to execute the test method body with `$this` bound to the proxy.

### 4. Integration with PHPUnit

Users add to their `phpunit.xml`:
```xml
<extensions>
    <bootstrap class="PHPUnit\InlineTests\Extension\InlineTestExtension">
        <parameter name="scanDirectories" value="src,app"/>
    </bootstrap>
</extensions>

<testsuites>
    <testsuite name="Inline Tests">
        <file>vendor/phpunit/inline-tests/tests/InlineTestsSuite.php</file>
    </testsuite>
</testsuites>
```

## Known Limitations

1. **eval() usage**: We use `eval()` to execute test method bodies with the proxy context. This has security implications if used with untrusted code.

2. **Exception expectations**: Tests using `expectException()` work correctly when run through PHPUnit's test runner, but may not work when calling `runInlineTest()` directly due to how eval executes code.

3. **Performance**: Scanning directories on every test run adds overhead. Consider caching discovered tests for large projects.

## Future Improvements

1. **Caching**: Cache discovered test classes to improve performance
2. **Better AST parsing**: Use nikic/php-parser instead of regex for class extraction
3. **Constructor parameters**: Support classes with constructor dependencies
4. **Better closure binding**: Find alternative to eval() for safer execution
5. **IDE support**: Generate stub files for better IDE autocomplete

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass: `vendor/bin/phpunit`
5. Ensure code passes PHPStan: `vendor/bin/phpstan analyse`
6. Submit a pull request

## Release Process

1. Update version in `composer.json`
2. Update CHANGELOG.md
3. Tag the release: `git tag v1.0.0`
4. Push tags: `git push --tags`
5. Create GitHub release
6. Submit to Packagist
