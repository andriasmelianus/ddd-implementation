<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use Illuminate\Support\Str;
use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Port\TokenGeneratorInterface;

final readonly class SanctumTokenGenerator implements TokenGeneratorInterface
{
    public function generateTokenFor(User $user): AuthTokenDto
    {
        // Menghasilkan Bearer access token unik dan aman
        $token = hash('sha256', Str::random(40).$user->id()->value().microtime(true));

        return new AuthTokenDto(
            accessToken: $token,
            tokenType: 'Bearer',
            userId: $user->id()->value(),
        );
    }
}

