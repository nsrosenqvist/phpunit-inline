<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the phpunit-inline-strip command.
 *
 * These tests copy fixtures to a temp directory, run the strip command,
 * and verify the results.
 */
final class StripCommandTest extends TestCase
{
    private static string $tempDir;
    private static string $fixturesDir;
    private static bool $commandExecuted = false;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesDir = dirname(__DIR__) . '/Fixtures/Strip';
        self::$tempDir = sys_get_temp_dir() . '/phpunit-inline-strip-test-' . uniqid();

        if (!mkdir(self::$tempDir, 0755, true)) {
            self::fail('Failed to create temp directory: ' . self::$tempDir);
        }

        // Copy all fixtures to temp directory
        $fixtures = glob(self::$fixturesDir . '/*.php');
        if ($fixtures === false || count($fixtures) === 0) {
            self::fail('No fixtures found in ' . self::$fixturesDir);
        }

        foreach ($fixtures as $fixture) {
            $dest = self::$tempDir . '/' . basename($fixture);
            copy($fixture, $dest);
        }

        // Run the strip command once during setup
        $binPath = dirname(__DIR__, 2) . '/bin/phpunit-inline-strip';
        $command = sprintf('%s %s 2>&1', escapeshellarg($binPath), escapeshellarg(self::$tempDir));

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail('Strip command failed with exit code ' . $exitCode . '. Output: ' . implode("\n", $output));
        }

        self::$commandExecuted = true;
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup temp directory
        if (is_dir(self::$tempDir)) {
            $files = glob(self::$tempDir . '/*') ?: [];
            array_map('unlink', $files);
            rmdir(self::$tempDir);
        }
    }

    #[Test]
    public function stripCommandExecutedSuccessfully(): void
    {
        $this->assertTrue(self::$commandExecuted, 'Strip command should have executed successfully');
    }

    #[Test]
    #[DataProvider('calculatorChecksProvider')]
    public function calculatorFileIsStrippedCorrectly(string $needle, bool $shouldContain, string $description): void
    {
        $content = file_get_contents(self::$tempDir . '/Calculator.php');
        $this->assertNotFalse($content, 'Failed to read Calculator.php');

        $contains = str_contains($content, $needle);

        if ($shouldContain) {
            $this->assertTrue($contains, "{$description} - should contain: {$needle}");
        } else {
            $this->assertFalse($contains, "{$description} - should not contain: {$needle}");
        }
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function calculatorChecksProvider(): array
    {
        return [
            'test attribute removed' => ['#[Test]', false, 'Test attribute should be removed'],
            'factory attribute removed' => ['#[Factory]', false, 'Factory attribute should be removed'],
            'dataprovider attribute removed' => ['#[DataProvider', false, 'DataProvider attribute should be removed'],
            'test methods removed' => ['function testAdd', false, 'Test methods should be removed'],
            'data providers removed' => ['function additionProvider', false, 'Data providers should be removed'],
            'add method preserved' => ['function add(', true, 'Production add() method should be preserved'],
            'subtract method preserved' => ['function subtract(', true, 'Production subtract() method should be preserved'],
            'multiply method preserved' => ['function multiply(', true, 'Private production multiply() method should be preserved'],
        ];
    }

    #[Test]
    #[DataProvider('namespaceBasedChecksProvider')]
    public function namespaceBasedFileIsStrippedCorrectly(string $needle, bool $shouldContain, string $description): void
    {
        $content = file_get_contents(self::$tempDir . '/NamespaceBased.php');
        $this->assertNotFalse($content, 'Failed to read NamespaceBased.php');

        $contains = str_contains($content, $needle);

        if ($shouldContain) {
            $this->assertTrue($contains, "{$description} - should contain: {$needle}");
        } else {
            $this->assertFalse($contains, "{$description} - should not contain: {$needle}");
        }
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function namespaceBasedChecksProvider(): array
    {
        return [
            'tests namespace removed' => ['namespace E2E\Strip\Tests', false, 'Tests namespace should be removed'],
            'test class removed' => ['class UserServiceTest', false, 'Test class should be removed'],
            'production namespace preserved' => ['namespace E2E\Strip;', true, 'Production namespace should be preserved'],
            'production class preserved' => ['class UserService', true, 'Production UserService class should be preserved'],
            'production user class preserved' => ['class User', true, 'Production User class should be preserved'],
        ];
    }

    #[Test]
    #[DataProvider('helpersChecksProvider')]
    public function helpersFileIsStrippedCorrectly(string $needle, bool $shouldContain, string $description): void
    {
        $content = file_get_contents(self::$tempDir . '/helpers.php');
        $this->assertNotFalse($content, 'Failed to read helpers.php');

        $contains = str_contains($content, $needle);

        if ($shouldContain) {
            $this->assertTrue($contains, "{$description} - should contain: {$needle}");
        } else {
            $this->assertFalse($contains, "{$description} - should not contain: {$needle}");
        }
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function helpersChecksProvider(): array
    {
        return [
            'test attribute removed' => ['#[Test]', false, 'Test attribute should be removed from functions'],
            'test functions removed' => ['function testSlugify', false, 'Test functions should be removed'],
            'data providers removed' => ['function priceProvider', false, 'Function data providers should be removed'],
            'slugify preserved' => ['function slugify', true, 'Production slugify() function should be preserved'],
            'formatPrice preserved' => ['function formatPrice', true, 'Production formatPrice() function should be preserved'],
        ];
    }

    #[Test]
    public function lifecycleFileIsStripped(): void
    {
        $content = file_get_contents(self::$tempDir . '/WithLifecycle.php');
        $this->assertNotFalse($content, 'Failed to read WithLifecycle.php');

        // Lifecycle methods should be removed
        $this->assertStringNotContainsString('#[Before]', $content);
        $this->assertStringNotContainsString('#[After]', $content);
        $this->assertStringNotContainsString('#[BeforeClass]', $content);
        $this->assertStringNotContainsString('#[AfterClass]', $content);
    }
}
