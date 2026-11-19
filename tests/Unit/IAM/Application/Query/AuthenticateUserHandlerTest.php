<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Application\Query;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Application\Query\AuthenticateUserHandler;
use Src\IAM\Application\Query\AuthenticateUserQuery;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Event\AccountSuspendedDueToFailedLogins;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\IAM\Domain\ValueObjects\HashedPassword;
use Src\IAM\Domain\ValueObjects\UserId;
use Tests\Unit\IAM\Support\FakePasswordHasher;
use Tests\Unit\IAM\Support\FakeTokenGenerator;
use Tests\Unit\IAM\Support\InMemoryUserRepository;
use Tests\Unit\IAM\Support\RecordingEventBus;

/**
 * Pure unit test for the CQRS orchestration layer.
 *
 * Every port is substituted with an in-memory fake, which demonstrates that
 * `AuthenticateUserHandler` depends only on abstractions (Hexagonal
 * Architecture) and contains orchestration — never business rules.
 */
#[CoversClass(AuthenticateUserHandler::class)]
final class AuthenticateUserHandlerTest extends TestCase
{
    private const string EMAIL = 'user@example.com';

    private const string VALID_PASSWORD = 'secret123';

    private const string PASSWORD_HASH = 'hash::secret123';

    #[Test]
    public function it_returns_an_auth_token_dto_on_successful_authentication(): void
    {
        $user = $this->makeUser(AccountStatus::ACTIVE);
        $repository = new InMemoryUserRepository($user);
        $tokenGenerator = new FakeTokenGenerator;

        $dto = $this->handler($repository, tokenGenerator: $tokenGenerator)(
            new AuthenticateUserQuery(new EmailAddress(self::EMAIL), self::VALID_PASSWORD)
        );

        self::assertInstanceOf(AuthTokenDto::class, $dto);
        self::assertSame('token-for-'.$user->id()->value(), $dto->accessToken);
        self::assertSame('Bearer', $dto->tokenType);
        self::assertTrue($dto->userId->equals($user->id()));
        self::assertSame(1, $tokenGenerator->generateCallCount);
    }

    #[Test]
    public function the_dto_serializes_to_the_documented_api_contract(): void
    {
        $repository = new InMemoryUserRepository($this->makeUser(AccountStatus::ACTIVE));

        $dto = $this->handler($repository)(
            new AuthenticateUserQuery(new EmailAddress(self::EMAIL), self::VALID_PASSWORD)
        );

        self::assertSame(
            ['access_token', 'token_type', 'user_id'],
            array_keys($dto->toArray()),
        );
    }

    #[Test]
    public function it_persists_the_successful_login_through_the_repository_port(): void
    {
        $user = $this->makeUser(AccountStatus::ACTIVE, failedLoginAttempts: 3);
        $repository = new InMemoryUserRepository($user);

        $this->handler($repository)(
            new AuthenticateUserQuery(new EmailAddress(self::EMAIL), self::VALID_PASSWORD)
        );

        self::assertSame(1, $repository->saveCallCount);
        self::assertSame(0, $user->failedLoginAttempts());
        self::assertNotNull($user->lastLoginAt());
    }

    #[Test]
    public function an_unknown_email_yields_the_same_generic_error_as_a_wrong_password(): void
    {
        $repository = new InMemoryUserRepository($this->makeUser(AccountStatus::ACTIVE));

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Email atau kata sandi yang Anda masukkan salah.');

        $this->handler($repository)(
            new AuthenticateUserQuery(new EmailAddress('ghost@example.com'), self::VALID_PASSWORD)
        );
    }

    #[Test]
    public function it_persists_the_failed_attempt_counter_before_rethrowing(): void
    {
        $user = $this->makeUser(AccountStatus::ACTIVE);
        $repository = new InMemoryUserRepository($user);
        $handler = $this->handler($repository);

        try {
            $handler(new AuthenticateUserQuery(new EmailAddress(self::EMAIL), 'wrong-password'));
            self::fail('InvalidCredentialsException was expected.');
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertSame(
            1,
            $repository->saveCallCount,
            'The brute-force counter is worthless unless the failure path also persists.',
        );
        self::assertSame(1, $user->failedLoginAttempts());
    }

    #[Test]
    public function it_publishes_the_suspension_event_when_the_attempt_limit_is_reached(): void
    {
        $user = $this->makeUser(AccountStatus::ACTIVE, failedLoginAttempts: 4);
        $repository = new InMemoryUserRepository($user);
        $eventBus = new RecordingEventBus;

        try {
            $this->handler($repository, eventBus: $eventBus)(
                new AuthenticateUserQuery(new EmailAddress(self::EMAIL), 'wrong-password')
            );
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertCount(1, $eventBus->publishedEvents);
        self::assertInstanceOf(AccountSuspendedDueToFailedLogins::class, $eventBus->publishedEvents[0]);
        self::assertSame(AccountStatus::SUSPENDED, $user->accountStatus());
        self::assertSame(1, $repository->saveCallCount);
    }

    #[Test]
    public function it_propagates_the_suspended_account_rejection_without_issuing_a_token(): void
    {
        $repository = new InMemoryUserRepository($this->makeUser(AccountStatus::SUSPENDED));
        $tokenGenerator = new FakeTokenGenerator;

        $this->expectException(AccountSuspendedException::class);

        try {
            $this->handler($repository, tokenGenerator: $tokenGenerator)(
                new AuthenticateUserQuery(new EmailAddress(self::EMAIL), self::VALID_PASSWORD)
            );
        } finally {
            self::assertSame(0, $tokenGenerator->generateCallCount);
        }
    }

    #[Test]
    public function it_propagates_the_unverified_account_rejection(): void
    {
        $repository = new InMemoryUserRepository($this->makeUser(AccountStatus::UNVERIFIED));

        $this->expectException(AccountUnverifiedException::class);
        $this->expectExceptionMessage('Silakan verifikasi email Anda terlebih dahulu.');

        $this->handler($repository)(
            new AuthenticateUserQuery(new EmailAddress(self::EMAIL), self::VALID_PASSWORD)
        );
    }

    #[Test]
    public function a_plain_successful_login_publishes_no_events(): void
    {
        $repository = new InMemoryUserRepository($this->makeUser(AccountStatus::ACTIVE));
        $eventBus = new RecordingEventBus;

        $this->handler($repository, eventBus: $eventBus)(
            new AuthenticateUserQuery(new EmailAddress(self::EMAIL), self::VALID_PASSWORD)
        );

        self::assertSame([], $eventBus->publishedEvents);
    }

    // ---------------------------------------------------------------------
    // Factories
    // ---------------------------------------------------------------------

    private function handler(
        InMemoryUserRepository $repository,
        ?FakePasswordHasher $hasher = null,
        ?FakeTokenGenerator $tokenGenerator = null,
        ?RecordingEventBus $eventBus = null,
    ): AuthenticateUserHandler {
        return new AuthenticateUserHandler(
            $repository,
            $hasher ?? new FakePasswordHasher,
            $tokenGenerator ?? new FakeTokenGenerator,
            $eventBus ?? new RecordingEventBus,
        );
    }

    private function makeUser(AccountStatus $status, int $failedLoginAttempts = 0): User
    {
        return User::reconstitute(
            id: UserId::random(),
            name: 'Dimas Islami',
            email: new EmailAddress(self::EMAIL),
            password: HashedPassword::fromString(self::PASSWORD_HASH),
            accountStatus: $status,
            failedLoginAttempts: $failedLoginAttempts,
            lastLoginAt: null,
            emailVerifiedAt: $status === AccountStatus::UNVERIFIED ? null : new DateTimeImmutable('2025-01-01'),
        );
    }
}
