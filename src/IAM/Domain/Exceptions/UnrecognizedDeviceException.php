<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exceptions;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Thrown when authentication originates from a device that is not recognised.
 */
final class UnrecognizedDeviceException extends DomainException implements VisibleExceptionInterface
{
    public const MESSAGE = 'Perangkat ini belum dikenali. Verifikasi perangkat diperlukan.';

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
