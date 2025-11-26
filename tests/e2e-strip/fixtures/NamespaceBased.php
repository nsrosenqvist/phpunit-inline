<?php

declare(strict_types=1);

namespace E2E\Strip;

/**
 * Service class with production code.
 */
class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {
    }

    public function getUser(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function createUser(string $name, string $email): User
    {
        $user = new User($name, $email);
        $this->repository->save($user);
        return $user;
    }
}

interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function save(User $user): void;
}

class User
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {
    }
}

// ==================== Tests ====================

namespace E2E\Strip\Tests;

use E2E\Strip\UserService;
use E2E\Strip\UserRepositoryInterface;
use E2E\Strip\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;

class UserServiceTest
{
    private UserService $service;
    private UserRepositoryInterface $repository;

    #[Before]
    public function setUp(): void
    {
        $this->repository = test()->createMock(UserRepositoryInterface::class);
        $this->service = new UserService($this->repository);
    }

    #[Test]
    public function testGetUserReturnsNullWhenNotFound(): void
    {
        $this->repository->method('find')->willReturn(null);
        test()->assertNull($this->service->getUser(999));
    }

    #[Test]
    public function testCreateUserSavesToRepository(): void
    {
        $this->repository->expects(test()->once())->method('save');
        $user = $this->service->createUser('John', 'john@example.com');
        test()->assertEquals('John', $user->name);
    }
}
