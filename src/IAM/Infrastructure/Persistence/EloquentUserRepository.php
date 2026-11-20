<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Persistence;

use DateTimeImmutable;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Ports\UserRepositoryInterface;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(Ulid $userId): ?User
    {
        $model = UserModel::query()->find($userId->getValue());

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        $model = UserModel::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email->value())])
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(User $user): void
    {
        $model = UserModel::query()->find($user->id->getValue()) ?? new UserModel;

        $model->forceFill([
            'id' => $user->id->getValue(),
            'email' => $user->email->value(),
            'password' => $user->hashedPassword(),
            'account_status' => $user->status()->value,
            'failed_login_attempts' => $user->failedLoginAttempts(),
            'last_login_at' => $user->lastLoginAt(),
        ]);

        $model->save();
        $user->pullEvents();
    }

    private function toDomain(UserModel $model): User
    {
        return User::reconstitute(
            id: new Ulid((string) $model->getKey()),
            email: EmailAddress::fromString((string) $model->email),
            hashedPassword: (string) $model->password,
            status: AccountStatus::from((string) $model->account_status),
            failedLoginAttempts: (int) $model->failed_login_attempts,
            lastLoginAt: $model->last_login_at === null
                ? null
                : DateTimeImmutable::createFromInterface($model->last_login_at),
        );
    }
}
