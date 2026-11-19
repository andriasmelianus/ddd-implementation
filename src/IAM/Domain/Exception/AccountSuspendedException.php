<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exception;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class AccountSuspendedException extends DomainException implements VisibleExceptionInterface
{
    public function __construct(string $message = 'Akun Anda sedang ditangguhkan.')
    {
        parent::__construct($message);
    }
}

