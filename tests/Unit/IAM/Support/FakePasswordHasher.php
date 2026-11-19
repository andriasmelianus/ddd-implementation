<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\IAM\Domain\Ports\PasswordHasherInterface;

/**
 * Implementasi {@see PasswordHasherInterface} untuk keperluan unit test.
 *
 * Memakai skema hash deterministik dan sangat sederhana (prefix + nilai)
 * agar unit test domain tetap cepat dan tidak bergantung pada bcrypt
 * maupun framework.
 */
final class FakePasswordHasher implements PasswordHasherInterface
{
    private const PREFIX = 'hashed::';

    public function hash(string $plainPassword): string
    {
        return self::PREFIX.$plainPassword;
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return hash_equals($hashedPassword, $this->hash($plainPassword));
    }
}
