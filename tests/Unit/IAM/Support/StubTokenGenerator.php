<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Ports\TokenGeneratorInterface;

/**
 * Implementasi {@see TokenGeneratorInterface} untuk unit test.
 *
 * Token-nya deterministik dan sengaja hanya memuat ID user — tidak ada
 * data sensitif — agar sesuai kontrak port.
 */
final class StubTokenGenerator implements TokenGeneratorInterface
{
    public function generateTokenFor(User $user): string
    {
        return 'token-for-'.$user->id->getValue();
    }
}
