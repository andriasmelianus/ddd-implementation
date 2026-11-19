<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Ports\UserRepositoryInterface;
use Src\IAM\Domain\ValueObjects\EmailAddress;

/**
 * Implementasi in-memory {@see UserRepositoryInterface} untuk unit test.
 *
 * Membuktikan kontrak port dapat dipenuhi tanpa database, sekaligus
 * dipakai sebagai test double pada pengujian lapisan Application.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<string, User> Ter-index berdasarkan email ter-normalisasi.
     */
    private array $users = [];

    public function findByEmail(EmailAddress $email): ?User
    {
        return $this->users[$this->key($email)] ?? null;
    }

    public function save(User $user): void
    {
        $this->users[$this->key($user->email)] = $user;
    }

    public function count(): int
    {
        return count($this->users);
    }

    /**
     * Normalisasi email menjadi kunci penyimpanan.
     *
     * {@see EmailAddress} hanya me-lowercase bagian domain, sehingga
     * kunci di-lowercase seluruhnya agar pencocokan bersifat
     * case-insensitive — menyerupai kolom database `CITEXT`/collation
     * case-insensitive pada implementasi nyata.
     */
    private function key(EmailAddress $email): string
    {
        return mb_strtolower($email->value());
    }
}
