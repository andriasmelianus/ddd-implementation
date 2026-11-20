<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Application\Command;

use PHPUnit\Framework\TestCase;
use Src\IAM\Application\Command\AuthenticateWithDeviceCommand;
use Src\IAM\Application\Command\AuthenticateWithDeviceCommandHandler;
use Src\IAM\Domain\Entity\Device;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\Service\UserDeviceAuthenticationService;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\DeviceId;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;
use Tests\Unit\IAM\Support\FakePasswordHasher;
use Tests\Unit\IAM\Support\InMemoryDeviceRepository;
use Tests\Unit\IAM\Support\InMemoryUserRepository;
use Tests\Unit\IAM\Support\StubTokenGenerator;

final class AuthenticateWithDeviceCommandHandlerTest extends TestCase
{
    private const EMAIL = 'user@example.com';

    private const PASSWORD = 'correct-horse-battery-staple';

    private InMemoryUserRepository $users;

    private InMemoryDeviceRepository $devices;

    private FakePasswordHasher $hasher;

    private AuthenticateWithDeviceCommandHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = new InMemoryUserRepository();
        $this->devices = new InMemoryDeviceRepository();
        $this->hasher = new FakePasswordHasher();
        $this->handler = new AuthenticateWithDeviceCommandHandler(
            $this->users,
            $this->devices,
            $this->hasher,
            new StubTokenGenerator(),
            new UserDeviceAuthenticationService($this->devices),
        );
    }

    public function test_it_authenticates_an_active_user_with_a_trusted_device(): void
    {
        $user = $this->givenUser();
        $deviceId = $this->givenTrustedDeviceFor($user);

        $token = ($this->handler)(new AuthenticateWithDeviceCommand(
            self::EMAIL,
            self::PASSWORD,
            $deviceId->value(),
            '203.0.113.10',
        ));

        self::assertSame('Bearer', $token->tokenType);
        self::assertSame($user->id->value(), $token->userId);
        self::assertSame('token-for-'.$user->id->value(), $token->accessToken);
        self::assertSame(0, $user->failedLoginAttempts());
        self::assertNotNull($user->lastLoginAt());
        self::assertSame('203.0.113.10', $this->devices->findById($deviceId)?->ipAddress());
    }

    public function test_it_persists_each_failed_password_attempt_and_suspends_on_the_fifth(): void
    {
        $user = $this->givenUser();
        $deviceId = $this->givenTrustedDeviceFor($user);

        for ($attempt = 1; $attempt <= User::MAX_FAILED_LOGIN_ATTEMPTS; ++$attempt) {
            try {
                ($this->handler)(new AuthenticateWithDeviceCommand(
                    self::EMAIL,
                    'invalid-password',
                    $deviceId->value(),
                    '203.0.113.10',
                ));
                self::fail('Expected invalid credentials.');
            } catch (InvalidCredentialsException) {
                self::assertSame($attempt, $user->failedLoginAttempts());
            }
        }

        self::assertTrue($user->isSuspended());
        self::assertSame($user, $this->users->findByEmail(EmailAddress::fromString(self::EMAIL)));
    }

    public function test_it_rejects_suspended_users_before_password_validation(): void
    {
        $user = $this->givenUser(AccountStatus::SUSPENDED);

        $this->expectException(AccountSuspendedException::class);

        ($this->handler)(new AuthenticateWithDeviceCommand(
            self::EMAIL,
            self::PASSWORD,
            DeviceId::generate()->value(),
            '203.0.113.10',
        ));
    }

    public function test_it_rejects_unverified_users_before_password_validation(): void
    {
        $user = $this->givenUser(AccountStatus::UNVERIFIED);

        $this->expectException(AccountUnverifiedException::class);

        ($this->handler)(new AuthenticateWithDeviceCommand(
            self::EMAIL,
            self::PASSWORD,
            DeviceId::generate()->value(),
            '203.0.113.10',
        ));
    }

    public function test_it_hides_an_unknown_email_as_invalid_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        ($this->handler)(new AuthenticateWithDeviceCommand(
            self::EMAIL,
            self::PASSWORD,
            DeviceId::generate()->value(),
            '203.0.113.10',
        ));
    }

    private function givenUser(AccountStatus $status = AccountStatus::ACTIVE): User
    {
        $user = User::reconstitute(
            Ulid::random(),
            EmailAddress::fromString(self::EMAIL),
            $this->hasher->hash(self::PASSWORD),
            $status,
            0,
        );
        $this->users->save($user);

        return $user;
    }

    private function givenTrustedDeviceFor(User $user): DeviceId
    {
        $deviceId = DeviceId::generate();
        $device = Device::create($deviceId, $user->id);
        $device->trust();
        $this->devices->save($device);

        return $deviceId;
    }
}

