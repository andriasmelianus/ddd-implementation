<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

/**
 * Trust state assigned to a device that belongs to an IAM user.
 */
enum DeviceStatus: string
{
    case TRUSTED = 'trusted';
    case UNTRUSTED = 'untrusted';
    case BLOCKED = 'blocked';

    public function isTrusted(): bool
    {
        return $this === self::TRUSTED;
    }

    public function isBlocked(): bool
    {
        return $this === self::BLOCKED;
    }
}
