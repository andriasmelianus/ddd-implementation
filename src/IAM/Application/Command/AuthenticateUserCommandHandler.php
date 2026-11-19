<?php

declare(strict_types=1);

namespace Src\IAM\Application\Command;

use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\Port\TokenGeneratorInterface;
use Src\IAM\Domain\Port\UserRepositoryInterface;
use Src\IAM\Domain\ValueObject\EmailAddress;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandHandlerInterface;

/**
 * @see User::authenticate()
 * ⚠️ ARCHITECTURAL EXCEPTION (CQRS):
 * Handler ini mengubah status autentikasi/keamanan user sekaligus mengembalikan AuthTokenDto
 * langsung ke pemanggil sesuai siklus hidup token autentikasi dan spesifikasi praktikum.
 */
final readonly class AuthenticateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenGeneratorInterface $tokenGenerator,
    ) {}

    public function __invoke(AuthenticateUserCommand $command): AuthTokenDto
    {
        $email = new EmailAddress($command->email);
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw new InvalidCredentialsException('Kredensial yang diberikan tidak valid.');
        }

        try {
            $user->authenticate($command->password, $this->passwordHasher);
        } catch (InvalidCredentialsException $e) {
            // Mitigasi State Persistence: Simpan penambahan attempt / status suspend ke DB sebelum melempar exception
            $this->userRepository->save($user);

            throw $e;
        }

        // Simpan pembaruan state sukses (failed attempts direset dan last_login_at diperbarui)
        $this->userRepository->save($user);

        return $this->tokenGenerator->generateTokenFor($user);
    }
}
