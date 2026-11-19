<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\Entity;

use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Events\UserAuthenticated;
use Src\IAM\Domain\Events\UserAuthenticationFailed;
use Src\IAM\Domain\Events\UserSuspended;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\Entities\BaseAggregateRoot;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;
use Tests\Unit\IAM\Support\FakePasswordHasher;

class UserTest extends TestCase
{
    private const PASSWORD = 'correct-horse-battery-staple';

    private FakePasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hasher = new FakePasswordHasher;
    }

    private function user(
        AccountStatus $status = AccountStatus::ACTIVE,
        int $failedLoginAttempts = 0,
    ): User {
        return User::reconstitute(
            id: Ulid::random(),
            email: EmailAddress::fromString('user@example.com'),
            hashedPassword: $this->hasher->hash(self::PASSWORD),
            status: $status,
            failedLoginAttempts: $failedLoginAttempts,
        );
    }

    public function test_it_is_an_aggregate_root_with_event_capabilities(): void
    {
        $user = $this->user();

        $this->assertInstanceOf(BaseAggregateRoot::class, $user);
        $this->assertTrue(method_exists($user, 'pullEvents'));
    }

    public function test_a_new_user_starts_unverified_with_no_failed_attempts(): void
    {
        $user = User::create(
            id: Ulid::random(),
            email: EmailAddress::fromString('new@example.com'),
            hashedPassword: $this->hasher->hash(self::PASSWORD),
        );

        $this->assertSame(AccountStatus::UNVERIFIED, $user->status());
        $this->assertSame(0, $user->failedLoginAttempts());
    }

    public function test_reconstitute_does_not_record_any_event(): void
    {
        $this->assertSame([], $this->user()->pullEvents());
    }

    public function test_it_authenticates_an_active_user_with_the_correct_password(): void
    {
        $user = $this->user();

        $user->authenticate(self::PASSWORD, $this->hasher);

        $events = $user->pullEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserAuthenticated::class, $events[0]);
    }

    public function test_a_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = $this->user(failedLoginAttempts: 3);

        $user->authenticate(self::PASSWORD, $this->hasher);

        $this->assertSame(0, $user->failedLoginAttempts());
    }

    public function test_a_wrong_password_throws_invalid_credentials(): void
    {
        $user = $this->user();

        $this->expectException(InvalidCredentialsException::class);

        $user->authenticate('wrong-password', $this->hasher);
    }

    public function test_a_wrong_password_increments_the_failed_attempt_counter(): void
    {
        $user = $this->user();

        foreach ([1, 2, 3] as $expected) {
            try {
                $user->authenticate('wrong-password', $this->hasher);
                $this->fail('Expected authentication to fail.');
            } catch (InvalidCredentialsException) {
                $this->assertSame($expected, $user->failedLoginAttempts());
            }
        }
    }

    public function test_a_wrong_password_records_an_authentication_failed_event(): void
    {
        $user = $this->user();

        try {
            $user->authenticate('wrong-password', $this->hasher);
        } catch (InvalidCredentialsException) {
            // expected
        }

        $events = $user->pullEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserAuthenticationFailed::class, $events[0]);
        $this->assertSame(1, $events[0]->failedLoginAttempts);
    }

    public function test_it_auto_suspends_on_the_fifth_consecutive_failure(): void
    {
        $user = $this->user();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $user->authenticate('wrong-password', $this->hasher);
            } catch (InvalidCredentialsException) {
                // expected
            }
            $this->assertSame(
                AccountStatus::ACTIVE,
                $user->status(),
                sprintf('Should still be active after %d failures.', $attempt),
            );
        }

        try {
            $user->authenticate('wrong-password', $this->hasher);
        } catch (InvalidCredentialsException) {
            // expected
        }

        $this->assertSame(5, $user->failedLoginAttempts());
        $this->assertSame(AccountStatus::SUSPENDED, $user->status());
    }

    public function test_auto_suspension_records_a_user_suspended_event(): void
    {
        $user = $this->user(failedLoginAttempts: 4);

        try {
            $user->authenticate('wrong-password', $this->hasher);
        } catch (InvalidCredentialsException) {
            // expected
        }

        $events = $user->pullEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(UserAuthenticationFailed::class, $events[0]);
        $this->assertInstanceOf(UserSuspended::class, $events[1]);
        $this->assertSame(5, $events[1]->failedLoginAttempts);
    }

    public function test_the_counter_freezes_once_the_account_is_suspended(): void
    {
        $user = $this->user(status: AccountStatus::SUSPENDED, failedLoginAttempts: 5);

        try {
            $user->authenticate('wrong-password', $this->hasher);
        } catch (InvalidCredentialsException) {
            // expected
        }

        $this->assertSame(5, $user->failedLoginAttempts());
    }

    public function test_a_suspended_user_with_the_correct_password_is_refused(): void
    {
        $user = $this->user(status: AccountStatus::SUSPENDED);

        $this->expectException(AccountSuspendedException::class);

        $user->authenticate(self::PASSWORD, $this->hasher);
    }

    public function test_an_unverified_user_with_the_correct_password_is_refused(): void
    {
        $user = $this->user(status: AccountStatus::UNVERIFIED);

        $this->expectException(AccountUnverifiedException::class);

        $user->authenticate(self::PASSWORD, $this->hasher);
    }

    public function test_a_wrong_password_on_an_inactive_account_reports_generic_credentials_error(): void
    {
        $user = $this->user(status: AccountStatus::UNVERIFIED);

        $this->expectException(InvalidCredentialsException::class);

        $user->authenticate('wrong-password', $this->hasher);
    }

    public function test_the_correct_password_on_an_inactive_account_does_not_increment_the_counter(): void
    {
        $user = $this->user(status: AccountStatus::UNVERIFIED, failedLoginAttempts: 2);

        try {
            $user->authenticate(self::PASSWORD, $this->hasher);
        } catch (AccountUnverifiedException) {
            // expected
        }

        $this->assertSame(2, $user->failedLoginAttempts());
    }

    public function test_pull_events_empties_the_buffer(): void
    {
        $user = $this->user();
        $user->authenticate(self::PASSWORD, $this->hasher);

        $this->assertCount(1, $user->pullEvents());
        $this->assertSame([], $user->pullEvents());
    }

    public function test_it_reports_remaining_login_attempts(): void
    {
        $this->assertSame(5, $this->user()->remainingLoginAttempts());
        $this->assertSame(2, $this->user(failedLoginAttempts: 3)->remainingLoginAttempts());
        $this->assertSame(0, $this->user(failedLoginAttempts: 9)->remainingLoginAttempts());
    }
}
