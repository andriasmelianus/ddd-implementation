<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Events;

use Src\Shared\Framework\Domain\Events\DomainEvent;

/**
 * Class UserSuspended
 *
 * Domain event yang menandakan akun user ditangguhkan secara otomatis
 * karena percobaan login gagal mencapai ambang batas.
 */
final class UserSuspended extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly int $failedLoginAttempts,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'iam.user.suspended';
    }
}
