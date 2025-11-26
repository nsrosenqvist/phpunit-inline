<?php

declare(strict_types=1);

namespace Acme\Service;

final class EmailService
{
    /** @var array<string> */
    private array $sentEmails = [];

    public function send(string $to, string $subject): void
    {
        $this->sentEmails[] = $to . ': ' . $subject;
    }

    /**
     * @return array<string>
     */
    public function getSentEmails(): array
    {
        return $this->sentEmails;
    }
}

namespace Acme\Service\Tests;

use Acme\Service\EmailService;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Namespace-based tests with lifecycle methods.
 */
final class EmailServiceTests
{
    /** @var array<string> */
    public static array $executionLog = [];

    private EmailService $service;

    #[BeforeClass]
    public static function setUpClass(): void
    {
        \Acme\Service\Tests\EmailServiceTests::$executionLog = [];
        \Acme\Service\Tests\EmailServiceTests::$executionLog[] = 'beforeClass';
    }

    #[Before]
    public function setUpService(): void
    {
        \Acme\Service\Tests\EmailServiceTests::$executionLog[] = 'before';
        $this->service = new EmailService();
    }

    #[Test]
    public function itSendsEmails(): void
    {
        \Acme\Service\Tests\EmailServiceTests::$executionLog[] = 'itSendsEmails';
        $this->service->send('test@example.com', 'Hello');

        test()->assertCount(1, $this->service->getSentEmails());
        test()->assertContains('test@example.com: Hello', $this->service->getSentEmails());
    }

    #[Test]
    public function itStartsWithEmptyList(): void
    {
        \Acme\Service\Tests\EmailServiceTests::$executionLog[] = 'itStartsWithEmptyList';
        // Service should be fresh from setUp
        test()->assertEmpty($this->service->getSentEmails());
    }

    #[After]
    public function cleanUp(): void
    {
        \Acme\Service\Tests\EmailServiceTests::$executionLog[] = 'after';
    }

    #[AfterClass]
    public static function tearDownClass(): void
    {
        \Acme\Service\Tests\EmailServiceTests::$executionLog[] = 'afterClass';
    }

    public static function resetLog(): void
    {
        \Acme\Service\Tests\EmailServiceTests::$executionLog = [];
    }
}
