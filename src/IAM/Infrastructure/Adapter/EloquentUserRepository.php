<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Port\UserRepositoryInterface;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\UserId;
use Src\IAM\Infrastructure\Persistence\UserHydrator;
use Src\IAM\Infrastructure\Persistence\UserModel;

/**
 * Adapter konkret {@see UserRepositoryInterface} berbasis Eloquent
 * (model **Write** CQRS).
 *
 * Tanggung jawab kelas ini murni teknis: menjalankan query, lalu mendelegasikan
 * pemetaan primitif ⇄ Value Object kepada {@see UserHydrator}. Tidak ada
 * keputusan bisnis di sini — seluruh invariant tetap milik Aggregate Root.
 *
 * 🚨 Critical Rule #2 (N+1): seluruh method hanya mengeksekusi satu query dan
 * tidak pernah melakukan query di dalam loop.
 */
final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private UserHydrator $hydrator,
    ) {}

    /**
     * Aturan Bisnis #2: lookup berdasarkan email, `null` bila tidak ada.
     *
     * Email dibandingkan dalam bentuk ternormalisasi (lowercase) karena
     * Value Object {@see EmailAddress} sudah menjamin normalisasi.
     */
    public function findByEmail(EmailAddress $email): ?User
    {
        $model = UserModel::query()
            ->where('email', '=', $email->value())
            ->first();

        if (! $model instanceof UserModel) {
            return null;
        }

        return $this->hydrator->toDomain($model);
    }

    public function findById(UserId $userId): ?User
    {
        $model = UserModel::query()
            ->whereKey($userId->value())
            ->first();

        if (! $model instanceof UserModel) {
            return null;
        }

        return $this->hydrator->toDomain($model);
    }

    /**
     * Mempersistensi state Aggregate Root menggunakan satu operasi upsert.
     */
    public function save(User $user): void
    {
        $row = $this->hydrator->toRow($user);

        UserModel::query()->updateOrCreate(
            ['id' => $row['id']],
            $row,
        );
    }
}
