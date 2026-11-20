<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Events;

/**
 * Records suspension after repeated failed login attempts.
 */
final readonly class UserSuspended
{
    public function __construct(
        public string $userId,
        public int $failedLoginAttempts,
    ) {
    }

    public function getName(): string
    {
        return 'iam.user.suspended';
    }
}
