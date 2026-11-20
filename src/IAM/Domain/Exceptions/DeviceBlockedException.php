<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exceptions;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Thrown when a blocked device attempts to authenticate.
 */
final class DeviceBlockedException extends DomainException implements VisibleExceptionInterface
{
    public const MESSAGE = 'Perangkat ini telah diblokir.';

    protected $code = 403;

    public function __construct(string $message = self::MESSAGE)
    {
        parent::__construct($message);
    }

    public static function create(): self
    {
        return new self();
    }
}
