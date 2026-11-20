<?php

declare(strict_types=1);

namespace Src\IAM\Application\Command;

use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\Ports\DeviceRepositoryInterface;
use Src\IAM\Domain\Ports\PasswordHasherInterface;
use Src\IAM\Domain\Ports\TokenGeneratorInterface;
use Src\IAM\Domain\Ports\UserRepositoryInterface;
use Src\IAM\Domain\Service\UserDeviceAuthenticationService;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\IAM\Domain\ValueObjects\EmailAddress;

/**
 * Orchestrates device-bound authentication without depending on HTTP, Eloquent,
 * or other infrastructure details.
 *
 * This security use case returns a token immediately after state persistence;
 * it is therefore an explicit exception to normal void command handling.
 */
final readonly class AuthenticateWithDeviceCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private DeviceRepositoryInterface $deviceRepository,
        private PasswordHasherInterface $passwordHasher,
        private TokenGeneratorInterface $tokenGenerator,
        private UserDeviceAuthenticationService $deviceAuthenticationService,
    ) {
    }

    /**
     * @throws AccountSuspendedException
     * @throws AccountUnverifiedException
     * @throws InvalidCredentialsException
     */
    public function __invoke(AuthenticateWithDeviceCommand $command): AuthTokenDto
    {
        $user = $this->userRepository->findByEmail(EmailAddress::fromString($command->email));

        if ($user === null) {
            throw InvalidCredentialsException::create();
        }

        if ($user->isSuspended()) {
            throw AccountSuspendedException::create();
        }

        if ($user->isUnverified()) {
            throw AccountUnverifiedException::create();
        }

        if (! $this->passwordHasher->verify($command->password, $user->hashedPassword())) {
            $user->incrementFailedAttempts();
            $this->userRepository->save($user);

            throw InvalidCredentialsException::create();
        }

        $device = $this->deviceAuthenticationService->authenticateDevice(
            $user,
            DeviceId::fromString($command->deviceId),
            $command->ipAddress,
        );

        $user->resetLoginAttempts();
        $user->recordLogin(new \DateTimeImmutable());

        $this->userRepository->save($user);
        $this->deviceRepository->save($device);

        return AuthTokenDto::bearer(
            $this->tokenGenerator->generateTokenFor($user),
            $user->id->value(),
        );
    }
}
