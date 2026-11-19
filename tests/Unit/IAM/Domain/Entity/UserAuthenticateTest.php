<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Event\AccountSuspendedDueToFailedLogins;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\HashedPassword;
use Src\IAM\Domain\ValueObjects\UserId;
use Tests\Unit\IAM\Support\FakePasswordHasher;

/**
 * PURE unit test for the `User` aggregate root.
 *
 * It extends PHPUnit's TestCase directly (NOT Tests\TestCase), therefore:
 *  - the Laravel kernel is never booted,
 *  - no database, container, facade or HTTP layer is involved,
 *  - the `PasswordHasherInterface` port is replaced by a deterministic
 *    in-memory fake adapter.
 *
 * This proves the Domain Layer is genuinely isolated (Hexagonal Architecture)
 * and that every login business rule is enforced inside the aggregate itself.
 */
#[CoversClass(User::class)]
final class UserAuthenticateTest extends TestCase
{
    private const string VALID_PASSWORD = 'secret123';

    private const string WRONG_PASSWORD = 'wrong-password';

    private const string PASSWORD_HASH = 'hash::secret123';

    // ---------------------------------------------------------------------
    // Scenario 1: successful login (Business Rule #5)
    // ---------------------------------------------------------------------

    #[Test]
    public function it_authenticates_an_active_user_with_the_correct_password(): void
    {
        $user = $this->makeUser(status: AccountStatus::ACTIVE);
        $loginMoment = new DateTimeImmutable('2026-01-15 10:30:00');

        $user->authenticate(self::VALID_PASSWORD, $this->hasher(), $loginMoment);

        self::assertSame(0, $user->failedLoginAttempts());
        self::assertSame($loginMoment, $user->lastLoginAt());
        self::assertSame(AccountStatus::ACTIVE, $user->accountStatus());
        self::assertSame([], $user->pullEvents(), 'A plain successful login must not emit domain events.');
    }

    #[Test]
    public function a_successful_login_resets_a_previously_accumulated_failure_counter(): void
    {
        $user = $this->makeUser(status: AccountStatus::ACTIVE, failedLoginAttempts: 4);

        $user->authenticate(self::VALID_PASSWORD, $this->hasher(), new DateTimeImmutable);

        self::assertSame(0, $user->failedLoginAttempts());
        self::assertSame(User::MAX_FAILED_LOGIN_ATTEMPTS, $user->remainingLoginAttempts());
    }

    // ---------------------------------------------------------------------
    // Scenario 2: wrong password (Business Rule #4)
    // ---------------------------------------------------------------------

    #[Test]
    public function it_rejects_a_wrong_password_with_a_generic_credentials_error(): void
    {
        $user = $this->makeUser(status: AccountStatus::ACTIVE);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Email atau kata sandi yang Anda masukkan salah.');

        $user->authenticate(self::WRONG_PASSWORD, $this->hasher(), new DateTimeImmutable);
    }

    #[Test]
    public function a_failed_login_never_updates_the_last_login_timestamp(): void
    {
        $previousLogin = new DateTimeImmutable('2025-12-01 08:00:00');
        $user = $this->makeUser(status: AccountStatus::ACTIVE, lastLoginAt: $previousLogin);

        try {
            $user->authenticate(self::WRONG_PASSWORD, $this->hasher(), new DateTimeImmutable);
            self::fail('InvalidCredentialsException was expected.');
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertSame($previousLogin, $user->lastLoginAt());
    }

    // ---------------------------------------------------------------------
    // Scenario 3: the failed-attempt counter (Business Rule #4)
    // ---------------------------------------------------------------------

    #[Test]
    public function it_increments_the_failed_attempt_counter_on_every_wrong_password(): void
    {
        $user = $this->makeUser(status: AccountStatus::ACTIVE);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $user->authenticate(self::WRONG_PASSWORD, $this->hasher(), new DateTimeImmutable);
                self::fail('InvalidCredentialsException was expected.');
            } catch (InvalidCredentialsException) {
                // expected
            }

            self::assertSame($attempt, $user->failedLoginAttempts());
            self::assertSame(
                AccountStatus::ACTIVE,
                $user->accountStatus(),
                'The account must stay ACTIVE below the suspension threshold.',
            );
        }

        self::assertSame(1, $user->remainingLoginAttempts());
        self::assertSame([], $user->pullEvents());
    }

    #[Test]
    public function it_suspends_the_account_and_records_a_domain_event_on_the_fifth_failure(): void
    {
        $user = $this->makeUser(status: AccountStatus::ACTIVE, failedLoginAttempts: 4);

        try {
            $user->authenticate(self::WRONG_PASSWORD, $this->hasher(), new DateTimeImmutable);
            self::fail('InvalidCredentialsException was expected.');
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertSame(User::MAX_FAILED_LOGIN_ATTEMPTS, $user->failedLoginAttempts());
        self::assertSame(AccountStatus::SUSPENDED, $user->accountStatus());
        self::assertSame(0, $user->remainingLoginAttempts());

        $events = $user->pullEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(AccountSuspendedDueToFailedLogins::class, $event);
        self::assertSame(User::MAX_FAILED_LOGIN_ATTEMPTS, $event->failedAttempts);
        self::assertSame('iam.account.suspended_due_to_failed_logins', $event->getName());
    }

    #[Test]
    public function the_auto_suspension_event_is_emitted_only_once(): void
    {
        $user = $this->makeUser(status: AccountStatus::ACTIVE, failedLoginAttempts: 4);

        try {
            $user->authenticate(self::WRONG_PASSWORD, $this->hasher(), new DateTimeImmutable);
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertCount(1, $user->pullEvents());

        // The account is now SUSPENDED, so further attempts are blocked upfront
        // and must not emit a duplicate suspension event.
        try {
            $user->authenticate(self::WRONG_PASSWORD, $this->hasher(), new DateTimeImmutable);
        } catch (AccountSuspendedException) {
            // expected
        }

        self::assertSame([], $user->pullEvents());
    }

    // ---------------------------------------------------------------------
    // Scenario 4: account status gate (Business Rule #3)
    // ---------------------------------------------------------------------

    #[Test]
    public function it_rejects_a_suspended_account_before_checking_the_password(): void
    {
        $user = $this->makeUser(status: AccountStatus::SUSPENDED);
        $hasher = $this->hasher();

        try {
            $user->authenticate(self::VALID_PASSWORD, $hasher, new DateTimeImmutable);
            self::fail('AccountSuspendedException was expected.');
        } catch (AccountSuspendedException $exception) {
            self::assertSame('Akun Anda sedang ditangguhkan.', $exception->getMessage());
        }

        self::assertSame(
            0,
            $hasher->verifyCallCount,
            'Business Rule #3: the status gate must short-circuit before any hash comparison.',
        );
        self::assertSame(0, $user->failedLoginAttempts(), 'A blocked attempt must not inflate the counter.');
    }

    #[Test]
    public function it_rejects_an_unverified_account_before_checking_the_password(): void
    {
        $user = $this->makeUser(status: AccountStatus::UNVERIFIED);
        $hasher = $this->hasher();

        try {
            $user->authenticate(self::VALID_PASSWORD, $hasher, new DateTimeImmutable);
            self::fail('AccountUnverifiedException was expected.');
        } catch (AccountUnverifiedException $exception) {
            self::assertSame('Silakan verifikasi email Anda terlebih dahulu.', $exception->getMessage());
        }

        self::assertSame(0, $hasher->verifyCallCount);
        self::assertNull($user->lastLoginAt());
    }

    #[Test]
    public function verifying_the_email_promotes_an_unverified_account_and_unblocks_login(): void
    {
        $user = $this->makeUser(status: AccountStatus::UNVERIFIED);

        $user->verifyEmail(new DateTimeImmutable('2026-01-10 09:00:00'));
        self::assertSame(AccountStatus::ACTIVE, $user->accountStatus());

        $user->authenticate(self::VALID_PASSWORD, $this->hasher(), new DateTimeImmutable);
        self::assertSame(0, $user->failedLoginAttempts());
    }

    // ---------------------------------------------------------------------
    // Factories
    // ---------------------------------------------------------------------

    private function makeUser(
        AccountStatus $status,
        int $failedLoginAttempts = 0,
        ?DateTimeImmutable $lastLoginAt = null,
    ): User {
        return User::reconstitute(
            id: UserId::random(),
            name: 'Dimas Islami',
            email: new EmailAddress('user@example.com'),
            password: HashedPassword::fromString(self::PASSWORD_HASH),
            accountStatus: $status,
            failedLoginAttempts: $failedLoginAttempts,
            lastLoginAt: $lastLoginAt,
            emailVerifiedAt: $status === AccountStatus::UNVERIFIED ? null : new DateTimeImmutable('2025-01-01'),
        );
    }

    private function hasher(): FakePasswordHasher&PasswordHasherInterface
    {
        return new FakePasswordHasher;
    }
}
