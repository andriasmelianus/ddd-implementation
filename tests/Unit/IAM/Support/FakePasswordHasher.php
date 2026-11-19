<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\ValueObjects\HashedPassword;

/**
 * Deterministic in-memory test double for the `PasswordHasherInterface` port.
 *
 * Hashing strategy is intentionally trivial — `hash::<plain>` — so assertions
 * stay readable and no bcrypt cost is paid during unit tests. This is the
 * practical pay-off of Hexagonal Architecture: the aggregate can be driven
 * without Laravel's Hash facade, a container, or any I/O.
 *
 * `verifyCallCount` lets tests assert *ordering* invariants, e.g. Business
 * Rule #3 requires the account-status gate to run BEFORE any hash comparison.
 */
final class FakePasswordHasher implements PasswordHasherInterface
{
    private const string PREFIX = 'hash::';

    public int $verifyCallCount = 0;

    public function verify(string $plainPassword, HashedPassword $hashedPassword): bool
    {
        $this->verifyCallCount++;

        if ($plainPassword === '') {
            return false;
        }

        return hash_equals($hashedPassword->value(), self::PREFIX.$plainPassword);
    }

    public function hash(string $plainPassword): HashedPassword
    {
        return HashedPassword::fromString(self::PREFIX.$plainPassword);
    }

    public function needsRehash(HashedPassword $hashedPassword): bool
    {
        return false;
    }
}
