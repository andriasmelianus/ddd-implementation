<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Entity;

use DateTimeImmutable;
use Src\IAM\Domain\Event\AccountSuspendedDueToFailedLogins;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\ValueObject\AccountStatus;
use Src\IAM\Domain\ValueObject\EmailAddress;
use Src\IAM\Domain\ValueObject\UserId;
use Src\Shared\Framework\Domain\Entities\BaseAggregateRoot;

final class User extends BaseAggregateRoot
{
    private function __construct(
        private readonly UserId $id,
        private string $name,
        private readonly EmailAddress $email,
        private string $password,
        private AccountStatus $accountStatus,
        private int $failedLoginAttempts = 0,
        private ?DateTimeImmutable $lastLoginAt = null,
        private ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    public static function create(
        UserId $id,
        string $name,
        EmailAddress $email,
        string $hashedPassword,
        AccountStatus $accountStatus = AccountStatus::ACTIVE,
        ?DateTimeImmutable $emailVerifiedAt = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            password: $hashedPassword,
            accountStatus: $accountStatus,
            failedLoginAttempts: 0,
            lastLoginAt: null,
            emailVerifiedAt: $emailVerifiedAt,
        );
    }

    public static function reconstitute(
        UserId $id,
        string $name,
        EmailAddress $email,
        string $hashedPassword,
        AccountStatus $accountStatus,
        int $failedLoginAttempts,
        ?DateTimeImmutable $lastLoginAt = null,
        ?DateTimeImmutable $emailVerifiedAt = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            password: $hashedPassword,
            accountStatus: $accountStatus,
            failedLoginAttempts: $failedLoginAttempts,
            lastLoginAt: $lastLoginAt,
            emailVerifiedAt: $emailVerifiedAt,
        );
    }

    /**
     * @throws AccountSuspendedException
     * @throws AccountUnverifiedException
     * @throws InvalidCredentialsException
     */
    public function authenticate(string $plainPassword, PasswordHasherInterface $hasher): void
    {
        // 1. Pengecekan status akun sebelum verifikasi password
        if ($this->accountStatus === AccountStatus::SUSPENDED) {
            throw new AccountSuspendedException('Akun Anda sedang ditangguhkan.');
        }

        if ($this->accountStatus === AccountStatus::UNVERIFIED) {
            throw new AccountUnverifiedException('Silakan verifikasi email Anda terlebih dahulu.');
        }

        // 2. Pencocokan kata sandi
        if (! $hasher->verify($plainPassword, $this->password)) {
            $this->failedLoginAttempts++;

            if ($this->failedLoginAttempts >= 5) {
                $this->accountStatus = AccountStatus::SUSPENDED;
                $this->recordEvent(new AccountSuspendedDueToFailedLogins(
                    userId: $this->id,
                    email: $this->email,
                    failedAttempts: $this->failedLoginAttempts,
                ));
            }

            throw new InvalidCredentialsException('Kredensial yang diberikan tidak valid.');
        }

        // 3. Login berhasil
        $this->failedLoginAttempts = 0;
        $this->lastLoginAt = new DateTimeImmutable;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function accountStatus(): AccountStatus
    {
        return $this->accountStatus;
    }

    public function failedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }
}
