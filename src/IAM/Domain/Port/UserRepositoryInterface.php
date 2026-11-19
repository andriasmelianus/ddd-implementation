<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Port;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\UserId;

/**
 * Port (Driven/Secondary Port) untuk persistensi Aggregate Root
 * {@see User} pada model **Write** CQRS.
 *
 * 🚨 Critical Rule #3 & #8:
 * - Application Layer (Command Handler) **wajib** mengakses database hanya
 *   melalui interface ini — bukan `DB::` maupun Eloquent Model langsung.
 * - Interface ini milik **Domain Layer** (inward), sedangkan implementasinya
 *   berada di Infrastructure Layer. Inilah inti *Dependency Inversion* pada
 *   Hexagonal Architecture: domain mendefinisikan kontrak, infrastruktur patuh.
 *
 * Kontrak ini sengaja bebas dari tipe Eloquent/Laravel apa pun.
 */
interface UserRepositoryInterface
{
    /**
     * Mencari pengguna berdasarkan alamat email.
     *
     * Aturan Bisnis #2: mengembalikan `null` bila tidak ditemukan. Pemanggil
     * (Command Handler) bertanggung jawab menerjemahkan `null` menjadi
     * {@see InvalidCredentialsException} dengan pesan
     * generik agar tidak terjadi *user enumeration*.
     */
    public function findByEmail(EmailAddress $email): ?User;

    /**
     * Mencari pengguna berdasarkan identitas.
     */
    public function findById(UserId $userId): ?User;

    /**
     * Mempersistensi perubahan state Aggregate Root.
     *
     * Digunakan baik untuk menyimpan kenaikan `failed_login_attempts`
     * (termasuk transisi status menjadi `SUSPENDED`) maupun untuk menyimpan
     * reset counter + `last_login_at` setelah login sukses.
     */
    public function save(User $user): void;
}
