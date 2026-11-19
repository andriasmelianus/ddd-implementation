<?php

declare(strict_types=1);

namespace Src\IAM\Application\Query;

use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\Ports\PasswordHasherInterface;
use Src\IAM\Domain\Ports\TokenGeneratorInterface;
use Src\IAM\Domain\Ports\UserRepositoryInterface;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBus;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryHandlerInterface;

/**
 * Class AuthenticateUserHandler
 *
 * Handler bagi {@see AuthenticateUserQuery}. Penamaan mengikuti konvensi
 * resolusi {@see QueryBus}
 * yang mengganti akhiran `Query` menjadi `Handler` (`AuthenticateUserQuery`
 * → `AuthenticateUserHandler`) — BUKAN `...QueryHandler`.
 *
 * Tugas handler ini murni **orkestrasi**, tanpa logika bisnis:
 * 1. Memuat aggregate user dari repository.
 * 2. Memanggil {@see User::authenticate()} —
 *    seluruh aturan (pencocokan password, pengecekan status, increment
 *    percobaan gagal, auto-suspend, reset hitungan) ada di dalam entitas.
 * 3. Mempersistensikan aggregate.
 * 4. Menerbitkan token dan mengembalikan {@see AuthTokenDto}.
 */
final readonly class AuthenticateUserHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenGeneratorInterface $tokenGenerator,
    ) {}

    /**
     * @throws InvalidCredentialsException Bila email tidak terdaftar atau password salah.
     * @throws AccountSuspendedException Bila akun ditangguhkan.
     * @throws AccountUnverifiedException Bila akun belum terverifikasi.
     */
    public function __invoke(AuthenticateUserQuery $query): AuthTokenDto
    {
        $user = $this->userRepository->findByEmail(
            EmailAddress::fromString($query->email),
        );

        // Email tidak terdaftar sengaja memakai exception yang SAMA dengan
        // password salah. Membedakan keduanya akan membocorkan email mana
        // yang terdaftar (user enumeration).
        if ($user === null) {
            throw InvalidCredentialsException::create();
        }

        try {
            $user->authenticate($query->password, $this->passwordHasher);
        } finally {
            // Dijalankan baik sukses maupun gagal: percobaan gagal mengubah
            // state aggregate (hitungan & auto-suspend) sehingga WAJIB
            // dipersistensi, kalau tidak auto-suspend tidak akan pernah
            // terjadi karena hitungannya selalu kembali ke nilai lama.
            $this->userRepository->save($user);
        }

        return AuthTokenDto::bearer(
            accessToken: $this->tokenGenerator->generateTokenFor($user),
            userId: $user->id->getValue(),
        );
    }
}
