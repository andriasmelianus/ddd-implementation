<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\Shared\Framework\Domain\Enums\TryFromOrNullTrait;

/**
 * Enum AccountStatus
 *
 * Immutable Value Object representing the lifecycle status of an account
 * within the IAM bounded context.
 *
 * Modelled as a string-backed enum so that each case can be persisted or
 * serialised as a string and reconstituted via {@see self::from()} or
 * {@see self::tryFrom()}, while giving the type system an exhaustive,
 * closed set of valid states.
 *
 * Lifecycle:
 * - UNVERIFIED: initial state after registration, awaiting verification.
 * - ACTIVE: verified and permitted to authenticate.
 * - SUSPENDED: access revoked administratively, may be reactivated.
 */
enum AccountStatus: string
{
    use TryFromOrNullTrait;

    /** Initial state after registration, awaiting verification. */
    case UNVERIFIED = 'unverified';

    /** Verified and permitted to authenticate. */
    case ACTIVE = 'active';

    /** Access revoked administratively, may be reactivated. */
    case SUSPENDED = 'suspended';

    /**
     * Human readable label for presentation layers.
     */
    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'Awaiting Verification',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
        };
    }

    /**
     * Whether an account in this status is allowed to authenticate.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Guard that asserts an account in this status may authenticate.
     *
     * Unlike {@see self::canAuthenticate()}, which merely reports, this
     * method enforces the rule by throwing a user-visible domain exception
     * describing precisely why access is refused.
     *
     * @throws AccountSuspendedException When the account is suspended.
     * @throws AccountUnverifiedException When the account is not yet verified.
     */
    public function ensureCanAuthenticate(): void
    {
        match ($this) {
            self::SUSPENDED => throw AccountSuspendedException::create(),
            self::UNVERIFIED => throw AccountUnverifiedException::create(),
            self::ACTIVE => null,
        };
    }

    /**
     * Whether a transition from the current status to `$target` is permitted.
     *
     * Allowed transitions:
     * - UNVERIFIED → ACTIVE (verification completed)
     * - UNVERIFIED → SUSPENDED (blocked before verification)
     * - ACTIVE → SUSPENDED (administrative suspension)
     * - SUSPENDED → ACTIVE (reactivation)
     *
     * Transitioning to the same status is not a transition and is rejected.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::UNVERIFIED => $target === self::ACTIVE || $target === self::SUSPENDED,
            self::ACTIVE => $target === self::SUSPENDED,
            self::SUSPENDED => $target === self::ACTIVE,
        };
    }
}
