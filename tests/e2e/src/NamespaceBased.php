<?php

declare(strict_types=1);

namespace E2E;

// Production code
class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array{email: string, name: string}|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->repository->findByEmail($email);
    }

    /**
     * @return array{email: string, name: string}
     */
    public function create(string $email, string $name): array
    {
        $user = ['email' => $email, 'name' => $name];
        $this->repository->save($user);
        return $user;
    }
}

interface UserRepositoryInterface
{
    /**
     * @return array{email: string, name: string}|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * @param array{email: string, name: string} $user
     */
    public function save(array $user): void;
}

// ==================== Tests ====================

namespace E2E\UserService\Tests;

use E2E\UserService;
use E2E\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Before;

class UserServiceTests
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
    public function testFindByEmailReturnsUser(): void
    {
        $this->repository->method('findByEmail')
            ->with('john@example.com')
            ->willReturn(['email' => 'john@example.com', 'name' => 'John']);

        $user = $this->service->findByEmail('john@example.com');

        test()->assertNotNull($user);
        test()->assertEquals('John', $user['name']);
    }

    #[Test]
    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $this->repository->method('findByEmail')
            ->willReturn(null);

        $result = $this->service->findByEmail('unknown@example.com');

        test()->assertNull($result);
    }

    #[Test]
    public function testCreateSavesUser(): void
    {
        $this->repository->expects(test()->once())
            ->method('save');

        $user = $this->service->create('jane@example.com', 'Jane');

        test()->assertEquals('jane@example.com', $user['email']);
        test()->assertEquals('Jane', $user['name']);
    }
}
