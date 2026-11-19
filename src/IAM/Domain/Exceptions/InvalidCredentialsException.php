<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exceptions;

use DomainException;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;

/**
 * Class InvalidCredentialsException
 *
 * Exception domain yang dilempar ketika proses autentikasi gagal karena
 * kombinasi kredensial (mis. email dan password) tidak cocok.
 *
 * ⚠️ **Catatan keamanan**: pesan exception ini sengaja dibuat generik dan
 * TIDAK membedakan antara "email tidak terdaftar" dan "password salah".
 * Membedakan keduanya membuka celah *user enumeration*, yaitu penyerang
 * dapat memakai response berbeda untuk memetakan email mana saja yang
 * terdaftar di sistem. Untuk alasan yang sama, exception ini tidak
 * menerima konteks email/password apa pun.
 *
 * Mengimplementasikan {@see VisibleExceptionInterface} karena pesan
 * generiknya aman untuk ditampilkan langsung kepada user.
 *
 * Kode exception = **401 Unauthorized**: identitas gagal dibuktikan.
 */
final class InvalidCredentialsException extends DomainException implements VisibleExceptionInterface
{
    /**
     * Pesan default yang ditampilkan kepada user.
     */
    public const MESSAGE = 'Email atau password yang Anda masukkan salah.';

    /**
     * Kode default exception ini = 401 (HTTP Unauthorized).
     *
     * @var int
     */
    protected $code = 401;

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
