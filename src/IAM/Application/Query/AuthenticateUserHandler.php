<?php

declare(strict_types=1);

namespace Src\IAM\Application\Query;

use DateTimeImmutable;
use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\Port\TokenGeneratorInterface;
use Src\IAM\Domain\Port\UserRepositoryInterface;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryHandlerInterface;
use Throwable;

/**
 * Handler untuk {@see AuthenticateUserQuery}.
 *
 * Perannya **hanya orkestrasi** (Application Layer):
 * load agregat → delegasikan keputusan ke Domain → persist → publish event.
 *
 * 🚨 Kepatuhan Critical Rules:
 * - #3: tidak ada `DB::` maupun Eloquent Model di sini; persistensi sepenuhnya
 *       melalui {@see UserRepositoryInterface}.
 * - #2: tidak ada query di dalam loop.
 * - Seluruh aturan bisnis (#3 status akun, #4 proteksi brute force,
 *       #5 reset counter) berada di Aggregate Root, bukan di handler ini.
 *
 * Catatan penting pada jalur kegagalan: ketika password salah, Aggregate Root
 * sudah menaikkan counter percobaan gagal. Karena itu handler **wajib**
 * menyimpan state tersebut sebelum melempar ulang exception, jika tidak
 * proteksi auto-suspend tidak akan pernah bekerja.
 */
final readonly class AuthenticateUserHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenGeneratorInterface $tokenGenerator,
        private EventBusInterface $eventBus,
    ) {}

    /**
     * @throws InvalidCredentialsException Akun tidak ditemukan atau password salah (pesan generik).
     * @throws AccountSuspendedException Akun ditangguhkan.
     * @throws AccountUnverifiedException Email belum diverifikasi.
     */
    public function __invoke(AuthenticateUserQuery $query): AuthTokenDto
    {
        // Aturan #2: lookup akun. Pesan generik agar tidak terjadi user enumeration.
        $user = $this->userRepository->findByEmail($query->email);

        if ($user === null) {
            throw InvalidCredentialsException::accountNotFound();
        }

        try {
            // Aturan #3, #4, #5 dieksekusi di dalam Aggregate Root.
            $user->authenticate(
                plainPassword: $query->plainPassword,
                passwordHasher: $this->passwordHasher,
                occurredAt: new DateTimeImmutable,
            );
        } catch (Throwable $exception) {
            // Simpan jejak percobaan gagal (termasuk transisi SUSPENDED) lalu
            // publikasikan event yang sudah direkam oleh agregat.
            $this->persistAndPublish($user);

            throw $exception;
        }

        // Aturan #5: persist reset counter + last_login_at.
        $this->persistAndPublish($user);

        return new AuthTokenDto(
            accessToken: $this->tokenGenerator->generateFor($user),
            tokenType: $this->tokenGenerator->tokenType(),
            userId: $user->id(),
        );
    }

    /**
     * Menyimpan agregat lalu melepaskan domain event **setelah** persistensi
     * berhasil, sehingga event tidak pernah dipublikasikan untuk perubahan
     * yang gagal tersimpan.
     */
    private function persistAndPublish(User $user): void
    {
        $this->userRepository->save($user);

        $events = $user->pullEvents();

        if ($events !== []) {
            $this->eventBus->publishEvents($events);
        }
    }
}
