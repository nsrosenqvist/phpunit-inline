<?php

declare(strict_types=1);

namespace Example\App;

use PHPUnit\Framework\Attributes\Test;

/**
 * Example application class demonstrating inline tests.
 *
 * This showcases how you can write tests directly alongside your production code,
 * similar to Rust's #[test] attribute approach.
 */
final class UserService
{
    /**
     * @param array<string> $users
     */
    public function __construct(
        private array $users = []
    ) {
    }

    public function addUser(string $username): void
    {
        if ($this->userExists($username)) {
            throw new \InvalidArgumentException("User {$username} already exists");
        }

        $this->users[] = $username;
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function getUserCount(): int
    {
        return count($this->users);
    }

    private function userExists(string $username): bool
    {
        return in_array($username, $this->users, true);
    }

    private function validateUsername(string $username): bool
    {
        return strlen($username) >= 3 && strlen($username) <= 20;
    }

    // ==================== Inline Tests ====================
    // These tests have access to both private methods and PHPUnit assertions

    #[Test]
    private function testAddUserAddsUserToList(): void
    {
        $this->addUser('john_doe');

        $this->assertCount(1, $this->users);
        $this->assertContains('john_doe', $this->users);
    }

    #[Test]
    private function testAddUserThrowsExceptionForDuplicateUser(): void
    {
        $this->addUser('jane_doe');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User jane_doe already exists');

        $this->addUser('jane_doe');
    }

    #[Test]
    private function testGetUserCountReturnsCorrectCount(): void
    {
        $this->addUser('user1');
        $this->addUser('user2');
        $this->addUser('user3');

        $count = $this->getUserCount();

        $this->assertEquals(3, $count);
    }

    #[Test]
    private function testUserExistsReturnsTrueForExistingUser(): void
    {
        // Test private method directly - this is the magic!
        $this->addUser('existing_user');

        $exists = $this->userExists('existing_user');

        $this->assertTrue($exists);
    }

    #[Test]
    private function testUserExistsReturnsFalseForNonExistingUser(): void
    {
        $exists = $this->userExists('nonexistent');

        $this->assertFalse($exists);
    }

    #[Test]
    private function testValidateUsernameAcceptsValidUsernames(): void
    {
        // Testing private validation logic
        $this->assertTrue($this->validateUsername('john'));
        $this->assertTrue($this->validateUsername('john_doe_123'));
        $this->assertTrue($this->validateUsername('a1234567890123456789')); // 20 chars
    }

    #[Test]
    private function testValidateUsernameRejectsInvalidUsernames(): void
    {
        $this->assertFalse($this->validateUsername('ab')); // too short
        $this->assertFalse($this->validateUsername('a123456789012345678901')); // too long
    }

    #[Test]
    protected function testCanUsePhpunitMockingFeatures(): void
    {
        // Demonstrate that PHPUnit mocking works too
        $mock = $this->createMock(\stdClass::class);

        $this->assertInstanceOf(\stdClass::class, $mock);
    }
}
