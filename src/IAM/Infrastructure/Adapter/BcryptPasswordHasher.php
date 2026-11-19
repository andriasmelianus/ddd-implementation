<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use Illuminate\Contracts\Hashing\Hasher;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\ValueObjects\HashedPassword;

/**
 * Adapter konkret untuk {@see PasswordHasherInterface} berbasis bcrypt
 * (melalui kontrak Hasher Laravel).
 *
 * Inilah sisi "driven adapter" pada Hexagonal Architecture: algoritma hashing
 * adalah detail teknis yang dapat ditukar (argon2id, dsb.) tanpa menyentuh
 * Domain maupun Application Layer.
 */
final readonly class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private Hasher $hasher,
    ) {}

    /**
     * Verifikasi password. `Hasher::check()` menggunakan `password_verify()`
     * yang aman terhadap *timing attack*.
     */
    public function verify(string $plainPassword, HashedPassword $hashedPassword): bool
    {
        if ($plainPassword === '') {
            return false;
        }

        return $this->hasher->check($plainPassword, $hashedPassword->value());
    }

    public function hash(string $plainPassword): HashedPassword
    {
        return new HashedPassword($this->hasher->make($plainPassword));
    }

    public function needsRehash(HashedPassword $hashedPassword): bool
    {
        return $this->hasher->needsRehash($hashedPassword->value());
    }
}
