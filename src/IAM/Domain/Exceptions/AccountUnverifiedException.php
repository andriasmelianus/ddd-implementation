<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exceptions;

use DomainException;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Class AccountUnverifiedException
 *
 * Exception domain yang dilempar ketika sebuah akun dengan status
 * {@see AccountStatus::UNVERIFIED} mencoba
 * melakukan operasi yang mensyaratkan akun terverifikasi (mis. autentikasi).
 *
 * Mengimplementasikan {@see VisibleExceptionInterface} karena pesannya
 * memang ditujukan untuk ditampilkan langsung kepada user.
 *
 * Kode exception = **403 Forbidden**: kredensial boleh jadi benar, namun
 * akun belum memenuhi syarat verifikasi email.
 */
final class AccountUnverifiedException extends DomainException implements VisibleExceptionInterface
{
    /**
     * Pesan default yang ditampilkan kepada user.
     */
    public const MESSAGE = 'Silakan verifikasi email Anda terlebih dahulu.';

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
