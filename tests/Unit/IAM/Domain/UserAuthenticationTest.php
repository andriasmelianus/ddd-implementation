<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Event\AccountSuspendedDueToFailedLogins;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\ValueObject\AccountStatus;
use Src\IAM\Domain\ValueObject\EmailAddress;
use Src\IAM\Domain\ValueObject\UserId;

final class UserAuthenticationTest extends TestCase
{
    private PasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = $this->createMock(PasswordHasherInterface::class);
    }

    public function test_successful_authentication_resets_attempts_and_updates_last_login(): void
    {
        $user = User::reconstitute(
            id: UserId::random(),
            name: 'John Doe',
            email: new EmailAddress('john@example.com'),
            hashedPassword: '$2y$12$hashedPasswordExample',
            accountStatus: AccountStatus::ACTIVE,
            failedLoginAttempts: 3,
            lastLoginAt: null,
            emailVerifiedAt: new DateTimeImmutable,
        );

        $this->hasher->expects($this->once())
            ->method('verify')
            ->with('correct-password', '$2y$12$hashedPasswordExample')
            ->willReturn(true);

        $user->authenticate('correct-password', $this->hasher);

        $this->assertSame(0, $user->failedLoginAttempts());
        $this->assertNotNull($user->lastLoginAt());
        $this->assertSame(AccountStatus::ACTIVE, $user->accountStatus());
        $this->assertEmpty($user->pullEvents());
    }

    public function test_wrong_password_increments_failed_attempts_and_throws_invalid_credentials_exception(): void
    {
        $user = User::reconstitute(
            id: UserId::random(),
            name: 'John Doe',
            email: new EmailAddress('john@example.com'),
            hashedPassword: '$2y$12$hashedPasswordExample',
            accountStatus: AccountStatus::ACTIVE,
            failedLoginAttempts: 1,
            lastLoginAt: null,
            emailVerifiedAt: new DateTimeImmutable,
        );

        $this->hasher->expects($this->once())
            ->method('verify')
            ->with('wrong-password', '$2y$12$hashedPasswordExample')
            ->willReturn(false);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Kredensial yang diberikan tidak valid.');

        try {
            $user->authenticate('wrong-password', $this->hasher);
        } finally {
            $this->assertSame(2, $user->failedLoginAttempts());
            $this->assertSame(AccountStatus::ACTIVE, $user->accountStatus());
            $this->assertNull($user->lastLoginAt());
            $this->assertEmpty($user->pullEvents());
        }
    }

    public function test_reaches_fifth_failed_attempt_auto_suspends_account_and_records_domain_event(): void
    {
        $user = User::reconstitute(
            id: UserId::random(),
            name: 'John Doe',
            email: new EmailAddress('john@example.com'),
            hashedPassword: '$2y$12$hashedPasswordExample',
            accountStatus: AccountStatus::ACTIVE,
            failedLoginAttempts: 4,
            lastLoginAt: null,
            emailVerifiedAt: new DateTimeImmutable,
        );

        $this->hasher->expects($this->once())
            ->method('verify')
            ->with('wrong-password-5th', '$2y$12$hashedPasswordExample')
            ->willReturn(false);

        $this->expectException(InvalidCredentialsException::class);

        try {
            $user->authenticate('wrong-password-5th', $this->hasher);
        } finally {
            $this->assertSame(5, $user->failedLoginAttempts());
            $this->assertSame(AccountStatus::SUSPENDED, $user->accountStatus());

            $events = $user->pullEvents();
            $this->assertCount(1, $events);
            $this->assertInstanceOf(AccountSuspendedDueToFailedLogins::class, $events[0]);
            $this->assertSame($user->id(), $events[0]->userId);
            $this->assertSame(5, $events[0]->failedAttempts);
        }
    }

    public function test_suspended_account_throws_exception_without_checking_password(): void
    {
        $user = User::reconstitute(
            id: UserId::random(),
            name: 'Suspended User',
            email: new EmailAddress('suspended@example.com'),
            hashedPassword: '$2y$12$hashedPasswordExample',
            accountStatus: AccountStatus::SUSPENDED,
            failedLoginAttempts: 5,
        );

        $this->hasher->expects($this->never())->method('verify');

        $this->expectException(AccountSuspendedException::class);
        $this->expectExceptionMessage('Akun Anda sedang ditangguhkan.');

        $user->authenticate('any-password', $this->hasher);
    }

    public function test_unverified_account_throws_exception_without_checking_password(): void
    {
        $user = User::reconstitute(
            id: UserId::random(),
            name: 'Unverified User',
            email: new EmailAddress('unverified@example.com'),
            hashedPassword: '$2y$12$hashedPasswordExample',
            accountStatus: AccountStatus::UNVERIFIED,
            failedLoginAttempts: 0,
        );

        $this->hasher->expects($this->never())->method('verify');

        $this->expectException(AccountUnverifiedException::class);
        $this->expectExceptionMessage('Silakan verifikasi email Anda terlebih dahulu.');

        $user->authenticate('any-password', $this->hasher);
    }
}
