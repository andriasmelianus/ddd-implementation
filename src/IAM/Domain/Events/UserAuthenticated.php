<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Events;

/**
 * Records a successful IAM authentication.
 */
final readonly class UserAuthenticated
{
    public function __construct(public string $userId)
    {
    }

    public function getName(): string
    {
        return 'iam.user.authenticated';
    }
}
