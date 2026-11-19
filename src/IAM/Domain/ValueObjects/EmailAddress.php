<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

use JsonSerializable;
use Src\Shared\Common\Exceptions\InvalidEmailException;
use Stringable;

/**
 * Value Object `EmailAddress`.
 *
 * Merepresentasikan alamat email pengguna pada Bounded Context IAM.
 *
 * Aturan Bisnis #1 (Validasi Sintaksis Email):
 * Seluruh validasi format email dieksekusi di dalam konstruktor
 * (*self-validating constructor*) sehingga **tidak mungkin** ada instance
 * `EmailAddress` dengan nilai tidak valid beredar di dalam sistem.
 * Format tidak valid memicu {@see InvalidEmailException}.
 *
 * Karakteristik:
 * - `final readonly` → immutable, tidak dapat diubah setelah dibuat.
 * - Nilai dinormalisasi (trim + lowercase) agar perbandingan konsisten.
 * - Perbandingan dilakukan berdasarkan nilai melalui {@see self::equals()}.
 */
final readonly class EmailAddress implements JsonSerializable, Stringable
{
    /**
     * Panjang maksimum alamat email sesuai batas praktis RFC 5321.
     */
    private const int MAX_LENGTH = 255;

    /**
     * Panjang maksimum bagian lokal (sebelum tanda `@`) sesuai RFC 5321.
     */
    private const int MAX_LOCAL_PART_LENGTH = 64;

    /**
     * Nilai email yang telah tervalidasi dan ternormalisasi.
     */
    public string $value;

    /**
     * @param  string  $value  Alamat email mentah dari luar domain.
     *
     * @throws InvalidEmailException Bila email kosong, terlalu panjang, atau formatnya tidak valid.
     */
    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidEmailException::empty();
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidEmailException::tooLong(self::MAX_LENGTH);
        }

        if (! self::isValidEmail($trimmed)) {
            throw InvalidEmailException::invalidFormat($trimmed);
        }

        $this->value = mb_strtolower($trimmed);
    }

    /**
     * Named constructor untuk membangun VO dari string.
     *
     * @throws InvalidEmailException
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Named constructor yang mengembalikan `null` bila input `null`.
     *
     * @throws InvalidEmailException
     */
    public static function fromStringOrNull(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return new self($value);
    }

    /**
     * Mengambil nilai primitif email.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Bagian lokal email (sebelum tanda `@`).
     */
    public function localPart(): string
    {
        $atPosition = (int) strrpos($this->value, '@');

        return substr($this->value, 0, $atPosition);
    }

    /**
     * Bagian domain email (setelah tanda `@`).
     */
    public function domain(): string
    {
        $atPosition = (int) strrpos($this->value, '@');

        return substr($this->value, $atPosition + 1);
    }

    /**
     * Membandingkan kesetaraan berdasarkan nilai (bukan identitas objek).
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Validasi format email: sintaksis umum + batas panjang bagian lokal.
     */
    private static function isValidEmail(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        if (substr_count($email, '@') !== 1) {
            return false;
        }

        [$localPart, $domain] = explode('@', $email, 2);

        if ($localPart === '' || mb_strlen($localPart) > self::MAX_LOCAL_PART_LENGTH) {
            return false;
        }

        // Domain wajib memiliki minimal satu titik (mis. "example.com").
        return str_contains($domain, '.');
    }
}
