<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use Illuminate\Support\Facades\Hash;
use Src\IAM\Domain\Ports\PasswordHasherInterface;

final readonly class BcryptPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return Hash::driver('bcrypt')->make($plainPassword);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return Hash::driver('bcrypt')->check($plainPassword, $hashedPassword);
    }
}
