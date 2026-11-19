<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

use Src\Shared\Framework\Domain\Enums\TryFromOrNullTrait;

/**
 * Value Object (Backed Enum) `AccountStatus`.
 *
 * Merepresentasikan status siklus hidup akun pengguna. Dimodelkan sebagai
 * *backed enum* karena himpunan nilainya tertutup (hanya tiga kemungkinan),
 * sehingga status ilegal tidak mungkin terbentuk.
 *
 * Aturan Bisnis #3 (Pengecekan Status Akun):
 * - `SUSPENDED`   → akun ditangguhkan, login ditolak.
 * - `UNVERIFIED`  → email belum terverifikasi, login ditolak.
 * - `ACTIVE`      → satu-satunya status yang boleh melanjutkan ke pencocokan password.
 *
 * Pengetahuan "status mana yang boleh login" sengaja ditempatkan di sini
 * (bukan di Handler/Controller) agar logika bisnis tetap berada di Domain Layer.
 */
enum AccountStatus: string
{
    use TryFromOrNullTrait;

    /**
     * Akun aktif dan berhak melakukan autentikasi.
     */
    case ACTIVE = 'active';

    /**
     * Akun ditangguhkan (mis. akibat 5 kali percobaan login gagal).
     */
    case SUSPENDED = 'suspended';

    /**
     * Akun terdaftar namun email belum diverifikasi.
     */
    case UNVERIFIED = 'unverified';

    /**
     * Apakah status ini mengizinkan proses autentikasi dilanjutkan?
     */
    public function canAuthenticate(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function isUnverified(): bool
    {
        return $this === self::UNVERIFIED;
    }

    /**
     * Label manusiawi untuk kebutuhan presentasi/logging.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::SUSPENDED => 'Ditangguhkan',
            self::UNVERIFIED => 'Belum Terverifikasi',
        };
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
