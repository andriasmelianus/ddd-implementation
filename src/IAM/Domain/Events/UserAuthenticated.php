<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Events;

use Src\Shared\Framework\Domain\Events\DomainEvent;

/**
 * Class UserAuthenticated
 *
 * Domain event yang menandakan seorang user **berhasil** diautentikasi.
 */
final class UserAuthenticated extends DomainEvent
{
    public function __construct(public readonly string $userId)
    {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'iam.user.authenticated';
    }
}
