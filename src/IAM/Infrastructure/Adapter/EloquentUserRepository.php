<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use DateTimeImmutable;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Port\UserRepositoryInterface;
use Src\IAM\Domain\ValueObject\AccountStatus;
use Src\IAM\Domain\ValueObject\EmailAddress;
use Src\IAM\Domain\ValueObject\UserId;
use Src\IAM\Infrastructure\Persistence\UserModel;

final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByEmail(EmailAddress $email): ?User
    {
        /** @var UserModel|null $model */
        $model = UserModel::query()->where('email', $email->value())->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findById(UserId $id): ?User
    {
        /** @var UserModel|null $model */
        $model = UserModel::query()->where('id', $id->value())->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function save(User $user): void
    {
        UserModel::query()->updateOrCreate(
            ['id' => $user->id()->value()],
            [
                'name' => $user->name(),
                'email' => $user->email()->value(),
                'password' => $user->password(),
                'account_status' => $user->accountStatus()->value,
                'failed_login_attempts' => $user->failedLoginAttempts(),
                'last_login_at' => $user->lastLoginAt()?->format('Y-m-d H:i:s'),
                'email_verified_at' => $user->emailVerifiedAt()?->format('Y-m-d H:i:s'),
            ]
        );

        // Lepaskan event domain dan pancarkan ke event dispatcher Laravel
        foreach ($user->pullEvents() as $event) {
            event($event);
        }
    }

    private function toDomain(UserModel $model): User
    {
        $lastLoginAt = $model->last_login_at !== null
            ? DateTimeImmutable::createFromInterface($model->last_login_at)
            : null;

        $emailVerifiedAt = $model->email_verified_at !== null
            ? DateTimeImmutable::createFromInterface($model->email_verified_at)
            : null;

        return User::reconstitute(
            id: UserId::fromString((string) $model->id),
            name: (string) $model->name,
            email: new EmailAddress((string) $model->email),
            hashedPassword: (string) $model->password,
            accountStatus: AccountStatus::from((string) $model->account_status),
            failedLoginAttempts: (int) $model->failed_login_attempts,
            lastLoginAt: $lastLoginAt,
            emailVerifiedAt: $emailVerifiedAt,
        );
    }
}

