<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exceptions;

use DomainException;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Class AccountSuspendedException
 *
 * Exception domain yang dilempar ketika sebuah akun dengan status
 * {@see AccountStatus::SUSPENDED} mencoba
 * melakukan operasi yang mensyaratkan akun aktif (mis. autentikasi).
 *
 * Mengimplementasikan {@see VisibleExceptionInterface} karena pesannya
 * memang ditujukan untuk ditampilkan langsung kepada user.
 *
 * Kode exception = **403 Forbidden**: identitas user diketahui, namun
 * aksesnya ditangguhkan.
 */
final class AccountSuspendedException extends DomainException implements VisibleExceptionInterface
{
    /**
     * Pesan default yang ditampilkan kepada user.
     */
    public const MESSAGE = 'Akun Anda sedang ditangguhkan.';

    /**
     * Kode default exception ini = 403 (HTTP Forbidden).
     *
     * @var int
     */
    protected $code = 403;

    public function __construct(string $message = self::MESSAGE)
    {
        parent::__construct($message);
    }

    /**
     * Named constructor untuk call-site yang lebih ekspresif.
     */
    public static function create(): self
    {
        return new self;
    }
}
