<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

use Src\IAM\Domain\Exception\InvalidHashedPasswordException;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Stringable;

/**
 * Value Object `HashedPassword`.
 *
 * Membungkus hash password yang tersimpan di persistensi. Tujuannya:
 * - Mencegah hash tertukar dengan password plaintext pada signature method.
 * - Menjamin hash tidak pernah kosong (invariant).
 * - Mencegah kebocoran hash melalui `var_dump()` / log via masking `__toString()`.
 *
 * ⚠️ Value Object ini **tidak** melakukan hashing maupun verifikasi. Algoritma
 * kriptografi adalah detail infrastruktur dan berada di balik port
 * {@see PasswordHasherInterface}.
 */
final readonly class HashedPassword implements Stringable
{
    /**
     * Nilai hash password.
     */
    public string $value;

    /**
     * @throws InvalidHashedPasswordException Bila hash kosong.
     */
    public function __construct(string $value)
    {
        if (trim($value) === '') {
            throw InvalidHashedPasswordException::empty();
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    /**
     * Sengaja dimasking agar hash tidak bocor ke log atau pesan error.
     */
    public function __toString(): string
    {
        return '[REDACTED]';
    }
}
