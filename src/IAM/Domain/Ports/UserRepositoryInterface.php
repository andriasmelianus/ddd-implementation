<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Ports;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * Interface UserRepositoryInterface
 *
 * **Write-side port** (Domain Port) untuk aggregate
 * {@see User}, sesuai critical rule #8 yang mewajibkan
 * pemisahan tegas antara interface Write (`*RepositoryInterface`) dan
 * interface Read (`*QueryInterface`).
 *
 * Kontrak ini HANYA untuk keperluan **write model**: memuat aggregate
 * guna dimutasi, lalu mempersistensikannya kembali. Interface ini
 * diinjeksikan ke **Command Handler**.
 *
 * ❌ Dilarang menambahkan method proyeksi/baca (mis. `search()`,
 * `paginate()`, `findAllActive()`) ke interface ini — gunakan
 * `UserQueryInterface` yang mengembalikan Read Model.
 *
 * Implementasi konkretnya diletakkan di lapisan Infrastructure
 * (`src/IAM/Infrastructure/Persistence`).
 */
interface UserRepositoryInterface
{
    /**
     * Memuat aggregate {@see User} berdasarkan identitas domainnya.
     */
    public function findById(Ulid $userId): ?User;

    /**
     * Memuat aggregate {@see User} berdasarkan email.
     *
     * Dipakai pada alur autentikasi: aggregate dimuat untuk dimutasi
     * (mis. increment percobaan gagal, auto-suspend, reset hitungan),
     * sehingga method ini sah berada di sisi write.
     *
     * Implementasi WAJIB mencocokkan email dalam bentuk ter-normalisasi
     * (lihat {@see EmailAddress} yang me-lowercase bagian domain) agar
     * `User@Example.com` dan `user@example.com` merujuk ke user yang sama.
     *
     * @param  EmailAddress  $email  Email yang dicari.
     * @return User|null Aggregate bila ditemukan, `null` bila tidak ada.
     */
    public function findByEmail(EmailAddress $email): ?User;

    /**
     * Mempersistensikan state aggregate (insert maupun update).
     *
     * Mengembalikan `void` sesuai critical rule #1 — ID aggregate sudah
     * ditentukan sebelum dipanggil, sehingga tidak ada nilai yang perlu
     * dikembalikan.
     *
     * Implementasi bertanggung jawab melepas domain event melalui
     * {@see User::pullEvents()} setelah persistensi berhasil, agar event
     * diteruskan ke EventBus.
     *
     * @param  User  $user  Aggregate yang akan disimpan.
     */
    public function save(User $user): void;
}
