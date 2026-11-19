<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Port\TokenGeneratorInterface;

/**
 * Predictable test double for the `TokenGeneratorInterface` port.
 *
 * Produces `token-for-<ulid>` so assertions can prove the token was minted
 * for the authenticated user — without depending on Sanctum, randomness,
 * or the service container.
 */
final class FakeTokenGenerator implements TokenGeneratorInterface
{
    public int $generateCallCount = 0;

    public function generateFor(User $user): string
    {
        $this->generateCallCount++;

        return 'token-for-'.$user->id()->value();
    }

    public function tokenType(): string
    {
        return 'Bearer';
    }
}
