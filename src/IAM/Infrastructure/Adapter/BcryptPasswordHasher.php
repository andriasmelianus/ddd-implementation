<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use Illuminate\Support\Facades\Hash;
use Src\IAM\Domain\Port\PasswordHasherInterface;

final readonly class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return Hash::make($plainPassword);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return Hash::check($plainPassword, $hashedPassword);
    }
}

