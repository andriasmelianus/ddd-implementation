<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Events;

use Src\Shared\Framework\Domain\Events\DomainEvent;

/**
 * Class UserAuthenticationFailed
 *
 * Domain event yang menandakan percobaan autentikasi **gagal** karena
 * password tidak cocok.
 *
 * Membawa `failedLoginAttempts` terkini agar listener (mis. monitoring
 * keamanan / rate limiter) dapat bereaksi tanpa perlu query ulang.
 */
final class UserAuthenticationFailed extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly int $failedLoginAttempts,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'iam.user.authentication_failed';
    }
}
