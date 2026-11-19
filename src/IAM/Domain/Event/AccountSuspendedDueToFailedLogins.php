<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Event;

use Src\IAM\Domain\ValueObject\EmailAddress;
use Src\IAM\Domain\ValueObject\UserId;
use Src\Shared\Framework\Domain\Events\DomainEvent;

final class AccountSuspendedDueToFailedLogins extends DomainEvent
{
    public function __construct(
        public readonly UserId $userId,
        public readonly EmailAddress $email,
        public readonly int $failedAttempts,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'iam.account_suspended_due_to_failed_logins';
    }
}

