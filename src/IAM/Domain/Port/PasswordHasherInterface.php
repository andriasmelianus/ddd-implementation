<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Port;

use Src\IAM\Domain\ValueObjects\HashedPassword;
use Src\IAM\Infrastructure\Adapter\BcryptPasswordHasher;

/**
 * Port untuk mekanisme hashing & verifikasi password.
 *
 * Algoritma kriptografi (bcrypt/argon2) adalah **detail infrastruktur** yang
 * dapat berubah tanpa memengaruhi aturan bisnis. Karena itu Domain Layer hanya
 * mengenal kontrak abstrak ini, sementara implementasi konkretnya
 * ({@see BcryptPasswordHasher}) berada di
 * Infrastructure Layer.
 *
 * Aturan Bisnis #4: verifikasi password **wajib** melalui port ini.
 */
interface PasswordHasherInterface
{
    /**
     * Memverifikasi password plaintext terhadap hash tersimpan.
     *
     * Implementasi wajib menggunakan perbandingan yang aman terhadap
     * *timing attack*.
     *
     * @param  string  $plainPassword  Password mentah dari input pengguna.
     * @param  HashedPassword  $hashedPassword  Hash tersimpan milik pengguna.
     */
    public function verify(string $plainPassword, HashedPassword $hashedPassword): bool;

    /**
     * Membuat hash baru dari password plaintext.
     */
    public function hash(string $plainPassword): HashedPassword;

    /**
     * Apakah hash perlu di-hash ulang (mis. karena cost factor berubah)?
     */
    public function needsRehash(HashedPassword $hashedPassword): bool;
}
