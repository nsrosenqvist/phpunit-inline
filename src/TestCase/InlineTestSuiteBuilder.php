<?php

declare(strict_types=1);

namespace PHPUnit\InlineTests\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestSuite;
use PHPUnit\InlineTests\Scanner\InlineTestClass;
use ReflectionAttribute;

/**
 * Builds PHPUnit TestSuite instances from inline test classes.
 */
final class InlineTestSuiteBuilder
{
    /**
     * Build a test suite from discovered inline test classes.
     *
     * @param array<InlineTestClass> $testClasses
     */
    public function build(array $testClasses): TestSuite
    {
        $suite = TestSuite::empty('Inline Tests');

        foreach ($testClasses as $testClass) {
            $classSuite = $this->buildClassSuite($testClass);
            $suite->addTest($classSuite);
        }

        return $suite;
    }

    /**
     * Build a test suite for a single inline test class.
     */
    private function buildClassSuite(InlineTestClass $testClass): TestSuite
    {
        $className = $testClass->getClassName();
        if ($className === '') {
            $className = 'Unknown';
        }
        $suite = TestSuite::empty($className);
        $reflection = $testClass->getReflection();

        foreach ($testClass->getTestMethods() as $testMethod) {
            // Check if the test method has a data provider
            $dataProviderName = $this->getDataProviderName($testMethod);

            if ($dataProviderName !== null) {
                // Create multiple test cases, one for each data set
                $dataSets = $this->getDataSets($reflection, $dataProviderName);

                foreach ($dataSets as $dataName => $dataSet) {
                    $testCase = InlineTestCase::createTest(
                        $reflection,
                        $testMethod,
                        $dataSet,
                        $dataName
                    );

                    $suite->addTest($testCase);
                }
            } else {
                // Single test case without data provider
                $testCase = InlineTestCase::createTest(
                    $reflection,
                    $testMethod
                );

                $suite->addTest($testCase);
            }
        }

        return $suite;
    }

    /**
     * Get the data provider method name from a test method.
     */
    private function getDataProviderName(\ReflectionMethod $method): ?string
    {
        $attributes = $method->getAttributes(
            DataProvider::class,
            ReflectionAttribute::IS_INSTANCEOF
        );

        if (empty($attributes)) {
            return null;
        }

        $attribute = $attributes[0]->newInstance();

        return $attribute->methodName();
    }

    /**
     * Get data sets from a data provider method.
     *
     * @param \ReflectionClass<object> $classReflection
     * @return array<int|string, array<mixed>>
     */
    private function getDataSets(\ReflectionClass $classReflection, string $providerMethodName): array
    {
        if (!$classReflection->hasMethod($providerMethodName)) {
            throw new \RuntimeException(
                sprintf(
                    'Data provider method %s::%s() does not exist',
                    $classReflection->getName(),
                    $providerMethodName
                )
            );
        }

        $providerMethod = $classReflection->getMethod($providerMethodName);
        $providerMethod->setAccessible(true);

        // Data provider methods are static, so we don't need an instance
        if ($providerMethod->isStatic()) {
            $data = $providerMethod->invoke(null);
        } else {
            // Non-static provider - need to create an instance
            // This might fail if the class has required constructor parameters
            $instance = $classReflection->newInstance();
            $data = $providerMethod->invoke($instance);
        }

        if (!is_array($data) && !($data instanceof \Traversable)) {
            throw new \RuntimeException(
                sprintf(
                    'Data provider %s::%s() must return an array or Traversable',
                    $classReflection->getName(),
                    $providerMethodName
                )
            );
        }

        // Convert to array and ensure proper structure
        $dataSets = [];
        $index = 0;

        foreach ($data as $key => $value) {
            // Each data set should be an array
            if (!is_array($value)) {
                $value = [$value];
            }

            // Use the key if it's a string, otherwise use the index
            $dataName = is_string($key) ? $key : $index;
            $dataSets[$dataName] = $value;
            $index++;
        }

        return $dataSets;
    }
}
