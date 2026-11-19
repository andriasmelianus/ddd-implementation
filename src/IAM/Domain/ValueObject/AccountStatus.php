<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObject;

enum AccountStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case UNVERIFIED = 'UNVERIFIED';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function isUnverified(): bool
    {
        return $this === self::UNVERIFIED;
    }
}

