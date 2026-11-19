<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\HashedPassword;
use Src\IAM\Domain\ValueObjects\UserId;

/**
 * Hydrator: menerjemahkan baris persistensi ({@see UserModel}) menjadi
 * Aggregate Root {@see User}, dan sebaliknya menjadi array kolom.
 *
 * 🚨 Critical Rule #5: hidrasi dilakukan **tanpa reflection**, murni melalui
 * factory method `User::reconstitute()`. Dengan demikian invariant entitas tetap
 * terjaga dan kontrak konstruktor tidak pernah dilangkahi.
 *
 * Kelas ini menjadi satu-satunya titik pemetaan primitif ⇄ Value Object,
 * sehingga perubahan skema tabel tidak merembet ke Domain Layer.
 */
final readonly class UserHydrator
{
    /**
     * Membangun Aggregate Root dari Eloquent Model.
     */
    public function toDomain(UserModel $model): User
    {
        return User::reconstitute(
            id: UserId::fromString((string) $model->id),
            name: (string) $model->name,
            email: EmailAddress::fromString((string) $model->email),
            password: HashedPassword::fromString((string) $model->password),
            accountStatus: AccountStatus::tryFrom((string) $model->account_status)
                ?? AccountStatus::UNVERIFIED,
            failedLoginAttempts: (int) $model->failed_login_attempts,
            lastLoginAt: $this->toDateTimeImmutable($model->last_login_at),
            emailVerifiedAt: $this->toDateTimeImmutable($model->email_verified_at),
        );
    }

    /**
     * Memetakan Aggregate Root menjadi array kolom persistensi.
     *
     * @return array<string,mixed>
     */
    public function toRow(User $user): array
    {
        return [
            'id' => $user->id()->value(),
            'name' => $user->name(),
            'email' => $user->email()->value(),
            'password' => $user->password()->value(),
            'account_status' => $user->accountStatus()->value,
            'failed_login_attempts' => $user->failedLoginAttempts(),
            'last_login_at' => $user->lastLoginAt()?->format('Y-m-d H:i:s'),
            'email_verified_at' => $user->emailVerifiedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Normalisasi nilai tanggal dari Eloquent menjadi `DateTimeImmutable`.
     */
    private function toDateTimeImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeImmutable();
        }

        return new DateTimeImmutable((string) $value);
    }
}
