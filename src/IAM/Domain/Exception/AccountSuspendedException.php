<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exception;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Dilempar ketika akun yang mencoba login berstatus `SUSPENDED`.
 *
 * Aturan Bisnis #3: pengecekan status akun dilakukan **sebelum** pencocokan
 * password, sehingga akun yang ditangguhkan ditolak tanpa membuka peluang
 * *brute force* terhadap hash password.
 *
 * Mengimplementasikan {@see VisibleExceptionInterface} sehingga pesannya aman
 * ditampilkan kepada klien HTTP.
 */
final class AccountSuspendedException extends DomainException implements VisibleExceptionInterface
{
    private const string DEFAULT_MESSAGE = 'Akun Anda sedang ditangguhkan.';

    public static function create(): self
    {
        return new self(self::DEFAULT_MESSAGE);
    }
}
