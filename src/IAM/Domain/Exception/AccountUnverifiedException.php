<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exception;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Dilempar ketika akun yang mencoba login belum memverifikasi email
 * (status `UNVERIFIED`).
 *
 * Aturan Bisnis #3: dievaluasi sebelum pencocokan password.
 */
final class AccountUnverifiedException extends DomainException implements VisibleExceptionInterface
{
    private const string DEFAULT_MESSAGE = 'Silakan verifikasi email Anda terlebih dahulu.';

    public static function create(): self
    {
        return new self(self::DEFAULT_MESSAGE);
    }
}
