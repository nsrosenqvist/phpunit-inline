<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\TestCase;

use PHPUnit\Framework\TestCase;
use NSRosenqvist\PHPUnitInline\Scanner\InlineTestClass;
use ReflectionClass;
use ReflectionMethod;

/**
 * Dynamic TestCase that wraps an application class instance and provides
 * access to both private methods and PHPUnit assertions.
 */
final class InlineTestCase extends TestCase
{
    private object $instance;
    private TestProxy $proxy;

    /** @var array<callable> */
    private static array $beforeClassCallbacks = [];
    /** @var array<callable> */
    private static array $afterClassCallbacks = [];
    private static bool $beforeClassExecuted = false;
    private static string $currentClassName = '';

    /**
     * @param ReflectionClass<object> $classReflection
     * @param InlineTestClass|null $testClass
     * @param array<mixed> $data Data set for parameterized tests
     * @param int|string $dataName Data set name/index
     */
    public static function createTest(
        ReflectionClass $classReflection,
        ReflectionMethod $testMethod,
        ?InlineTestClass $testClass = null,
        array $data = [],
        int|string $dataName = ''
    ): self {
        // Create instance with the runInlineTest method name
        $instance = new self('runInlineTest');
        $instance->setClassReflection($classReflection);
        $instance->setTestMethod($testMethod);

        // Create a minimal InlineTestClass if not provided
        if ($testClass === null) {
            $testClass = new InlineTestClass($classReflection, [$testMethod]);
        }

        $instance->setTestClass($testClass);
        $instance->setTestData($data);
        $instance->setDataName($dataName);

        // Register lifecycle methods if this is a new class
        if (self::$currentClassName !== $classReflection->getName()) {
            self::$currentClassName = $classReflection->getName();
            self::$beforeClassExecuted = false;
            self::$beforeClassCallbacks = [];
            self::$afterClassCallbacks = [];

            // Register BeforeClass and AfterClass callbacks
            foreach ($testClass->getBeforeClassMethods() as $method) {
                self::$beforeClassCallbacks[] = function () use ($method) {
                    if ($method instanceof \ReflectionMethod) {
                        $method->setAccessible(true);
                        $method->invoke(null);
                    }
                    // ReflectionFunction doesn't need setAccessible
                };
            }

            foreach ($testClass->getAfterClassMethods() as $method) {
                self::$afterClassCallbacks[] = function () use ($method) {
                    if ($method instanceof \ReflectionMethod) {
                        $method->setAccessible(true);
                        $method->invoke(null);
                    }
                    // ReflectionFunction doesn't need setAccessible
                };
            }
        }

        return $instance;
    }

    /** @var ReflectionClass<object> */
    private ReflectionClass $classReflection;
    private ReflectionMethod $testMethod;
    private InlineTestClass $testClass;
    /** @var array<mixed> */
    private array $testData = [];
    private int|string $dataName = '';

    /**
     * @param ReflectionClass<object> $classReflection
     */
    private function setClassReflection(ReflectionClass $classReflection): void
    {
        $this->classReflection = $classReflection;
    }

    private function setTestMethod(ReflectionMethod $testMethod): void
    {
        $this->testMethod = $testMethod;
    }

    private function setTestClass(InlineTestClass $testClass): void
    {
        $this->testClass = $testClass;
    }

    /**
     * @param array<mixed> $data
     */
    private function setTestData(array $data): void
    {
        $this->testData = $data;
    }

    private function setDataName(int|string $dataName): void
    {
        $this->dataName = $dataName;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Execute BeforeClass methods once
        if (!self::$beforeClassExecuted) {
            foreach (self::$beforeClassCallbacks as $callback) {
                $callback();
            }
            self::$beforeClassExecuted = true;
        }

        // Create a new instance of the application class
        $this->instance = $this->classReflection->newInstance();

        // Create the proxy that provides access to both contexts
        $this->proxy = new TestProxy(
            $this->instance,
            $this,
            $this->testMethod
        );

        // Execute Before methods on the instance
        foreach ($this->testClass->getBeforeMethods() as $method) {
            if ($method instanceof \ReflectionMethod) {
                $method->setAccessible(true);
                $method->invoke($this->instance);
            }
            // ReflectionFunction doesn't need setAccessible
        }
    }

    protected function tearDown(): void
    {
        // Execute After methods on the instance
        if (isset($this->instance) && isset($this->testClass)) {
            foreach ($this->testClass->getAfterMethods() as $method) {
                if ($method instanceof \ReflectionMethod) {
                    $method->setAccessible(true);
                    $method->invoke($this->instance);
                }
                // ReflectionFunction doesn't need setAccessible
            }
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        // Execute AfterClass methods
        foreach (self::$afterClassCallbacks as $callback) {
            $callback();
        }

        // Reset state
        self::$beforeClassExecuted = false;
        self::$beforeClassCallbacks = [];
        self::$afterClassCallbacks = [];
        self::$currentClassName = '';

        parent::tearDownAfterClass();
    }

    /**
     * The actual test method that executes the inline test.
     */
    public function runInlineTest(): void
    {
        // Ensure setUp was called (initialize proxy if not already done)
        if (!isset($this->proxy)) {
            $this->instance = $this->classReflection->newInstance();
            $this->proxy = new TestProxy(
                $this->instance,
                $this,
                $this->testMethod
            );
        }

        // Make the test method accessible
        $this->testMethod->setAccessible(true);

        // Execute the test method directly on the proxy
        // The proxy will route calls appropriately
        $this->proxy->execute($this->testData);
    }

    public function getProxy(): TestProxy
    {
        return $this->proxy;
    }

    /**
     * Override toString() to include data set name for better test output.
     */
    public function toString(): string
    {
        $name = $this->classReflection->getName() . '::' . $this->testMethod->getName();

        if ($this->dataName !== '' && $this->dataName !== 0) {
            $name .= ' with data set "' . $this->dataName . '"';
        } elseif (!empty($this->testData)) {
            $name .= ' with data set #' . $this->dataName;
        }

        return $name;
    }
}
