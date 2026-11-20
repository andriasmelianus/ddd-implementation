<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Events;

/**
 * Records an unsuccessful authentication attempt.
 */
final readonly class UserAuthenticationFailed
{
    public function __construct(
        public string $userId,
        public int $failedLoginAttempts,
    ) {
    }

    public function getName(): string
    {
        return 'iam.user.authentication_failed';
    }
}
