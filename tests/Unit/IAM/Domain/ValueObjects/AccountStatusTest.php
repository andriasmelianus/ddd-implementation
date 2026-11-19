<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\ValueObjects;

use PHPUnit\Framework\TestCase;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\Shared\Framework\Domain\Exceptions\VisibleExceptionInterface;
use ValueError;

class AccountStatusTest extends TestCase
{
    public function test_it_exposes_exactly_the_expected_cases(): void
    {
        $this->assertSame(
            ['unverified', 'active', 'suspended'],
            array_map(static fn (AccountStatus $case): string => $case->value, AccountStatus::cases()),
        );
    }

    public function test_it_reconstitutes_from_its_backed_value(): void
    {
        $this->assertSame(AccountStatus::ACTIVE, AccountStatus::from('active'));
        $this->assertSame(AccountStatus::SUSPENDED, AccountStatus::from('suspended'));
        $this->assertSame(AccountStatus::UNVERIFIED, AccountStatus::from('unverified'));
    }

    public function test_it_rejects_an_unknown_backed_value(): void
    {
        $this->expectException(ValueError::class);

        AccountStatus::from('deleted');
    }

    public function test_try_from_or_null_handles_null_and_unknown_values(): void
    {
        $this->assertNull(AccountStatus::tryFromOrNull(null));
        $this->assertNull(AccountStatus::tryFromOrNull('deleted'));
        $this->assertSame(AccountStatus::ACTIVE, AccountStatus::tryFromOrNull('active'));
    }

    public function test_only_an_active_account_can_authenticate(): void
    {
        $this->assertTrue(AccountStatus::ACTIVE->canAuthenticate());
        $this->assertFalse(AccountStatus::SUSPENDED->canAuthenticate());
        $this->assertFalse(AccountStatus::UNVERIFIED->canAuthenticate());
    }

    public function test_ensure_can_authenticate_passes_for_an_active_account(): void
    {
        $this->expectNotToPerformAssertions();

        AccountStatus::ACTIVE->ensureCanAuthenticate();
    }

    public function test_ensure_can_authenticate_rejects_a_suspended_account(): void
    {
        $this->expectException(AccountSuspendedException::class);
        $this->expectExceptionMessage('Akun Anda sedang ditangguhkan.');

        AccountStatus::SUSPENDED->ensureCanAuthenticate();
    }

    public function test_ensure_can_authenticate_rejects_an_unverified_account(): void
    {
        $this->expectException(AccountUnverifiedException::class);
        $this->expectExceptionMessage('Silakan verifikasi email Anda terlebih dahulu.');

        AccountStatus::UNVERIFIED->ensureCanAuthenticate();
    }

    public function test_guard_exceptions_are_user_visible_with_a_forbidden_code(): void
    {
        foreach ([AccountStatus::SUSPENDED, AccountStatus::UNVERIFIED] as $status) {
            try {
                $status->ensureCanAuthenticate();
                $this->fail(sprintf('%s should refuse authentication.', $status->value));
            } catch (AccountSuspendedException|AccountUnverifiedException $exception) {
                $this->assertInstanceOf(VisibleExceptionInterface::class, $exception);
                $this->assertSame(403, $exception->getCode());
            }
        }
    }

    public function test_it_exposes_a_label_for_every_case(): void
    {
        $this->assertSame('Awaiting Verification', AccountStatus::UNVERIFIED->label());
        $this->assertSame('Active', AccountStatus::ACTIVE->label());
        $this->assertSame('Suspended', AccountStatus::SUSPENDED->label());
    }

    public function test_it_allows_the_expected_lifecycle_transitions(): void
    {
        $this->assertTrue(AccountStatus::UNVERIFIED->canTransitionTo(AccountStatus::ACTIVE));
        $this->assertTrue(AccountStatus::UNVERIFIED->canTransitionTo(AccountStatus::SUSPENDED));
        $this->assertTrue(AccountStatus::ACTIVE->canTransitionTo(AccountStatus::SUSPENDED));
        $this->assertTrue(AccountStatus::SUSPENDED->canTransitionTo(AccountStatus::ACTIVE));
    }

    public function test_it_rejects_transitions_back_to_unverified(): void
    {
        $this->assertFalse(AccountStatus::ACTIVE->canTransitionTo(AccountStatus::UNVERIFIED));
        $this->assertFalse(AccountStatus::SUSPENDED->canTransitionTo(AccountStatus::UNVERIFIED));
    }

    public function test_it_rejects_a_transition_to_the_same_status(): void
    {
        foreach (AccountStatus::cases() as $status) {
            $this->assertFalse(
                $status->canTransitionTo($status),
                sprintf('%s should not transition to itself.', $status->value),
            );
        }
    }
}
