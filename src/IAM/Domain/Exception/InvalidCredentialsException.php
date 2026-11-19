<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exception;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Dilempar ketika kredensial login tidak cocok.
 *
 * Aturan Bisnis #2 & #4:
 * Exception ini digunakan untuk **dua** kondisi berbeda — akun tidak ditemukan
 * dan password salah — dengan **pesan generik yang identik**. Hal ini disengaja
 * demi keamanan: pesan spesifik akan membocorkan informasi email mana yang
 * terdaftar di sistem (*user enumeration attack*).
 *
 * Mengimplementasikan {@see VisibleExceptionInterface} sehingga pesannya aman
 * untuk ditampilkan kepada klien HTTP.
 */
final class InvalidCredentialsException extends DomainException implements VisibleExceptionInterface
{
    /**
     * Pesan generik tunggal — tidak boleh dibedakan antar penyebab.
     */
    private const string GENERIC_MESSAGE = 'Email atau kata sandi yang Anda masukkan salah.';

    /**
     * Akun dengan email tersebut tidak ditemukan.
     */
    public static function accountNotFound(): self
    {
        return new self(self::GENERIC_MESSAGE);
    }

    /**
     * Password tidak cocok dengan hash tersimpan.
     */
    public static function passwordMismatch(): self
    {
        return new self(self::GENERIC_MESSAGE);
    }
}
