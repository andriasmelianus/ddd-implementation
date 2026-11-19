<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Port;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Infrastructure\Adapter\SanctumTokenGenerator;

/**
 * Port untuk pembuatan access token setelah autentikasi berhasil.
 *
 * Aturan Bisnis #5: token dihasilkan melalui port ini, sehingga Domain &
 * Application Layer tidak bergantung pada mekanisme token tertentu
 * (Sanctum, JWT, Passport). Implementasi aktual berada di Infrastructure Layer
 * ({@see SanctumTokenGenerator}).
 */
interface TokenGeneratorInterface
{
    /**
     * Membuat access token untuk pengguna yang telah terautentikasi.
     *
     * @param  User  $user  Aggregate Root pengguna yang sudah lolos seluruh invariant login.
     * @return string Access token siap dikirim ke klien.
     */
    public function generateFor(User $user): string;

    /**
     * Tipe token yang dihasilkan (dikirim sebagai `token_type` pada response).
     */
    public function tokenType(): string;
}
