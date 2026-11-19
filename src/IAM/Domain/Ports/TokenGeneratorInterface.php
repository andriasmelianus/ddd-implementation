<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Ports;

use Src\IAM\Domain\Entity\User;

/**
 * Interface TokenGeneratorInterface
 *
 * **Port** yang mengabstraksi pembuatan token autentikasi (mis. JWT atau
 * personal access token) bagi seorang {@see User} yang sudah berhasil
 * diautentikasi.
 *
 * Domain hanya bergantung pada kontrak ini sehingga pustaka/strategi token
 * (JWT, Sanctum, Paseto, dsb.) dapat ditukar tanpa menyentuh domain.
 *
 * ⚠️ **CATATAN ARSITEKTUR**: pembuatan token adalah operasi
 * infrastruktur/keamanan yang nilainya WAJIB langsung dikembalikan ke
 * caller dan tidak dapat di-query ulang. Karena itu, pemanggilnya pada
 * lapisan Application harus berupa **Query** (mis. `GenerateTokenQuery`),
 * BUKAN Command — sebab Command wajib `void` menurut critical rule #1.
 * Pengecualian ini dinyatakan eksplisit pada critical rule #1.
 *
 * Adapter konkretnya diletakkan di lapisan Infrastructure.
 */
interface TokenGeneratorInterface
{
    /**
     * Membuat token autentikasi untuk user yang diberikan.
     *
     * Kontrak implementasi:
     * - Hanya boleh dipanggil untuk user yang SUDAH lolos
     *   {@see User::authenticate()}; interface ini tidak melakukan
     *   verifikasi kredensial apa pun.
     * - Token WAJIB dibangkitkan dengan sumber acak yang aman secara
     *   kriptografis (CSPRNG) atau ditandatangani dengan kunci rahasia.
     * - ❌ Token TIDAK BOLEH memuat data sensitif (mis. hash password).
     *
     * @param  User  $user  User yang sudah terautentikasi.
     * @return string Token siap dikirim ke client.
     */
    public function generateTokenFor(User $user): string;
}
