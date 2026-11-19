<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Ports\TokenGeneratorInterface;

/**
 * Local development token generator.
 *
 * The returned token is cryptographically random but stateless and is not
 * persisted. It is suitable only for exercising the login endpoint; it
 * cannot authenticate future requests until a token-verification mechanism
 * (such as Sanctum or JWT) is introduced.
 */
final readonly class LocalTokenGenerator implements TokenGeneratorInterface
{
    public function generateTokenFor(User $user): string
    {
        return bin2hex(random_bytes(32));
    }
}
