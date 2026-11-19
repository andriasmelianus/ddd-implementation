<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Port\UserRepositoryInterface;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\UserId;

/**
 * In-memory test double for the write-side `UserRepositoryInterface` port.
 *
 * Replaces Eloquent entirely, so handler tests execute with zero database
 * round-trips while still exercising the real aggregate. `saveCallCount`
 * is exposed to assert that the handler persists state even on the failure
 * path (otherwise the brute-force counter would silently be lost).
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> keyed by lowercase email */
    private array $usersByEmail = [];

    public int $saveCallCount = 0;

    public function __construct(User ...$users)
    {
        foreach ($users as $user) {
            $this->usersByEmail[$user->email()->value()] = $user;
        }
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        return $this->usersByEmail[$email->value()] ?? null;
    }

    public function findById(UserId $id): ?User
    {
        foreach ($this->usersByEmail as $user) {
            if ($user->id()->equals($id)) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $this->saveCallCount++;
        $this->usersByEmail[$user->email()->value()] = $user;
    }
}
