<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exception;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class AccountUnverifiedException extends DomainException implements VisibleExceptionInterface
{
    public function __construct(string $message = 'Silakan verifikasi email Anda terlebih dahulu.')
    {
        parent::__construct($message);
    }
}

