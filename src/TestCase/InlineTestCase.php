<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\TestCase;

use PHPUnit\Framework\TestCase;
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

    /**
     * @param ReflectionClass<object> $classReflection
     * @param array<mixed> $data Data set for parameterized tests
     * @param int|string $dataName Data set name/index
     */
    public static function createTest(
        ReflectionClass $classReflection,
        ReflectionMethod $testMethod,
        array $data = [],
        int|string $dataName = ''
    ): self {
        // Create instance with the runInlineTest method name
        $instance = new self('runInlineTest');
        $instance->setClassReflection($classReflection);
        $instance->setTestMethod($testMethod);
        $instance->setTestData($data);
        $instance->setDataName($dataName);

        return $instance;
    }

    /** @var ReflectionClass<object> */
    private ReflectionClass $classReflection;
    private ReflectionMethod $testMethod;
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

        // Create a new instance of the application class
        $this->instance = $this->classReflection->newInstance();

        // Create the proxy that provides access to both contexts
        $this->proxy = new TestProxy(
            $this->instance,
            $this,
            $this->testMethod
        );
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
