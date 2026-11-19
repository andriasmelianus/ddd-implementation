<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exception;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

final class InvalidEmailException extends DomainException implements VisibleExceptionInterface
{
    public function __construct(string $message = 'Format email tidak valid.')
    {
        parent::__construct($message);
    }
}

