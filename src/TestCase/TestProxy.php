<?php

declare(strict_types=1);

namespace NSRosenqvist\PHPUnitInline\TestCase;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Proxy object that provides access to both class methods and PHPUnit assertions.
 *
 * This class uses __call() to route method calls either to the application class
 * instance or the PHPUnit TestCase context.
 */
final class TestProxy
{
    public function __construct(
        private readonly object $instance,
        private readonly TestCase $testCase,
        private readonly ReflectionMethod $testMethod
    ) {
    }

    /**
     * Magic method to route calls to the appropriate target.
     *
     * @param array<mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        // First, check if the method exists on the instance (including private methods)
        $instanceClass = get_class($this->instance);

        if (method_exists($this->instance, $method)) {
            $reflection = new ReflectionMethod($instanceClass, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($this->instance, ...$args);
        }

        // Otherwise, delegate to the TestCase for assertions and other PHPUnit methods
        if (method_exists($this->testCase, $method)) {
            // Use reflection for protected methods
            $reflection = new ReflectionMethod($this->testCase, $method);

            if (!$reflection->isPublic()) {
                $reflection->setAccessible(true);
                return $reflection->invoke($this->testCase, ...$args);
            }

            return $this->testCase->$method(...$args);
        }

        throw new \BadMethodCallException(
            sprintf(
                'Method %s does not exist on %s or TestCase',
                $method,
                $instanceClass
            )
        );
    }

    /**
     * Magic method to get properties from the instance.
     */
    public function __get(string $name): mixed
    {
        $instanceClass = get_class($this->instance);
        $reflection = new \ReflectionClass($instanceClass);

        if ($reflection->hasProperty($name)) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            return $property->getValue($this->instance);
        }

        throw new \RuntimeException(
            sprintf(
                'Property %s does not exist on %s',
                $name,
                $instanceClass
            )
        );
    }

    /**
     * Magic method to set properties on the instance.
     */
    public function __set(string $name, mixed $value): void
    {
        $instanceClass = get_class($this->instance);
        $reflection = new \ReflectionClass($instanceClass);

        if ($reflection->hasProperty($name)) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($this->instance, $value);
            return;
        }

        // If property doesn't exist, create it dynamically on the instance
        $this->instance->$name = $value;
    }

    /**
     * Execute the test method by manually parsing and executing it.
     *
     * @param array<mixed> $args Arguments to pass to the test method
     */
    public function execute(array $args = []): void
    {
        $this->testMethod->setAccessible(true);

        // Create a wrapper that will execute the test method's code
        // with $this references redirected through our proxy
        $this->executeTestMethodWithProxy($args);
    }

    /**
     * Execute the test method code line by line, intercepting $this calls.
     *
     * Since we can't rebind closures for class methods, we use a different approach:
     * We invoke the method on the instance, but the instance's methods will need
     * to work with the proxy.
     *
     * Actually, let's use a simpler approach: execute the code via eval with
     * $this bound to proxy. But that's unsafe. Instead, let's use reflection
     * to manually execute the method body.
     *
     * @param array<mixed> $args Arguments to pass to the test method
     */
    private function executeTestMethodWithProxy(array $args): void
    {
        // Get the source code of the method
        $filename = $this->testMethod->getFileName();
        $startLine = $this->testMethod->getStartLine();
        $endLine = $this->testMethod->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            throw new \RuntimeException('Could not get method source information');
        }

        $source = file($filename);
        if ($source === false) {
            throw new \RuntimeException('Could not read source file');
        }

        // Extract method body (skip the method declaration line)
        $methodBody = implode('', array_slice($source, $startLine, $endLine - $startLine));

        // Remove the method signature and opening/closing braces
        $methodBody = preg_replace('/^.*?\{/s', '', $methodBody);
        if ($methodBody === null) {
            throw new \RuntimeException('Failed to extract method body');
        }
        $methodBody = preg_replace('/\}\s*$/s', '', $methodBody);

        if ($methodBody === null) {
            throw new \RuntimeException('Could not extract method body');
        }

        // Create a closure that executes the method body with $this as the proxy
        $executor = function () use ($methodBody, $args) {
            // Set up the test() helper
            global $__inlineTestCase;
            $__inlineTestCase = $this->testCase;

            // Extract parameters from $args array
            // Use PHP's extract to create variables from the data provider array
            if (!empty($args)) {
                // Get parameter names from the method
                $params = $this->testMethod->getParameters();
                foreach ($params as $index => $param) {
                    if (isset($args[$index])) {
                        ${$param->getName()} = $args[$index];
                    }
                }
            }

            eval($methodBody);
        };

        // Bind the closure to $this (the proxy)
        $boundExecutor = $executor->bindTo($this, $this);

        // @phpstan-ignore identical.alwaysFalse
        if ($boundExecutor === null) {
            throw new \RuntimeException('Could not bind executor to proxy');
        }        $boundExecutor();
    }

    public function getInstance(): object
    {
        return $this->instance;
    }

    public function getTestCase(): TestCase
    {
        return $this->testCase;
    }
}
