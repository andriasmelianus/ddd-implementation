<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Entity;

use DateTimeImmutable;
use Src\IAM\Domain\Events\UserAuthenticated;
use Src\IAM\Domain\Events\UserAuthenticationFailed;
use Src\IAM\Domain\Events\UserSuspended;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\Ports\PasswordHasherInterface;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * IAM user aggregate root. It is independent from framework and persistence code.
 */
final class User
{
    public const MAX_FAILED_LOGIN_ATTEMPTS = 5;

    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly Ulid $id,
        public readonly EmailAddress $email,
        private string $hashedPassword,
        private AccountStatus $status,
        private int $failedLoginAttempts,
        private ?DateTimeImmutable $lastLoginAt,
    ) {
    }

    public static function create(Ulid $id, EmailAddress $email, string $hashedPassword): self
    {
        return new self(
            $id,
            $email,
            $hashedPassword,
            AccountStatus::UNVERIFIED,
            0,
            null,
        );
    }

    public static function reconstitute(
        Ulid $id,
        EmailAddress $email,
        string $hashedPassword,
        AccountStatus $status,
        int $failedLoginAttempts,
        ?DateTimeImmutable $lastLoginAt = null,
    ): self {
        return new self(
            $id,
            $email,
            $hashedPassword,
            $status,
            max(0, $failedLoginAttempts),
            $lastLoginAt,
        );
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function failedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function hashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function isSuspended(): bool
    {
        return $this->status === AccountStatus::SUSPENDED;
    }

    public function isUnverified(): bool
    {
        return $this->status === AccountStatus::UNVERIFIED;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::ACTIVE;
    }

    public function authenticate(string $plainPassword, PasswordHasherInterface $hasher): void
    {
        if (! $hasher->verify($plainPassword, $this->hashedPassword)) {
            $this->incrementFailedAttempts();

            throw InvalidCredentialsException::create();
        }

        $this->status->ensureCanAuthenticate();
        $this->recordLogin(new DateTimeImmutable());
    }

    public function incrementFailedAttempts(): void
    {
        if ($this->isSuspended()) {
            $this->recordEvent(new UserAuthenticationFailed($this->id->value(), $this->failedLoginAttempts));

            return;
        }

        ++$this->failedLoginAttempts;
        $this->recordEvent(new UserAuthenticationFailed($this->id->value(), $this->failedLoginAttempts));

        if ($this->failedLoginAttempts >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
            $this->suspend();
        }
    }

    public function resetLoginAttempts(): void
    {
        $this->failedLoginAttempts = 0;
    }

    public function suspend(): void
    {
        if ($this->isSuspended()) {
            return;
        }

        $this->status = AccountStatus::SUSPENDED;
        $this->recordEvent(new UserSuspended($this->id->value(), $this->failedLoginAttempts));
    }

    public function recordLogin(DateTimeImmutable $loggedInAt): void
    {
        $this->lastLoginAt = $loggedInAt;
        $this->resetLoginAttempts();
        $this->recordEvent(new UserAuthenticated($this->id->value()));
    }

    public function remainingLoginAttempts(): int
    {
        return max(0, self::MAX_FAILED_LOGIN_ATTEMPTS - $this->failedLoginAttempts);
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function recordEvent(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

}
