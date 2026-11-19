<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Ports;

/**
 * Interface PasswordHasherInterface
 *
 * **Port** (dalam terminologi Hexagonal Architecture) yang mengabstraksi
 * mekanisme hashing dan verifikasi password.
 *
 * Domain hanya bergantung pada kontrak ini, bukan pada implementasi
 * konkret (mis. bcrypt/argon2 milik Laravel `Hash` facade). Dengan begitu:
 * - Domain tetap bebas dari dependensi framework (dependency rule DDD).
 * - Algoritma hashing dapat diganti/di-migrasi tanpa menyentuh entitas.
 * - Unit test aggregate dapat memakai implementasi in-memory/fake.
 *
 * Adapter konkretnya diletakkan di lapisan Infrastructure.
 */
interface PasswordHasherInterface
{
    /**
     * Meng-hash password mentah.
     *
     * @param  string  $plainPassword  Password mentah dari user.
     * @return string Hash password siap disimpan.
     */
    public function hash(string $plainPassword): string;

    /**
     * Memverifikasi apakah password mentah cocok dengan hash tersimpan.
     *
     * Implementasi WAJIB memakai perbandingan yang aman terhadap
     * *timing attack* (mis. `password_verify()`, yang sudah
     * constant-time secara internal).
     *
     * @param  string  $plainPassword  Password mentah yang diuji.
     * @param  string  $hashedPassword  Hash pembanding.
     * @return bool `true` bila cocok.
     */
    public function verify(string $plainPassword, string $hashedPassword): bool;
}
