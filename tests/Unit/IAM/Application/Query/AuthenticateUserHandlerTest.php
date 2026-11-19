<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Application\Query;

use PHPUnit\Framework\TestCase;
use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Application\Query\AuthenticateUserHandler;
use Src\IAM\Application\Query\AuthenticateUserQuery;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Events\UserAuthenticated;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;
use Src\IAM\Domain\ValueObjects\AccountStatus;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;
use Tests\Unit\IAM\Support\FakePasswordHasher;
use Tests\Unit\IAM\Support\InMemoryUserRepository;
use Tests\Unit\IAM\Support\StubTokenGenerator;

class AuthenticateUserHandlerTest extends TestCase
{
    private const EMAIL = 'user@example.com';

    private const PASSWORD = 'correct-horse-battery-staple';

    private FakePasswordHasher $hasher;

    private InMemoryUserRepository $repository;

    private AuthenticateUserHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hasher = new FakePasswordHasher;
        $this->repository = new InMemoryUserRepository;
        $this->handler = new AuthenticateUserHandler(
            $this->repository,
            $this->hasher,
            new StubTokenGenerator,
        );
    }

    private function givenUser(
        AccountStatus $status = AccountStatus::ACTIVE,
        int $failedLoginAttempts = 0,
    ): User {
        $user = User::reconstitute(
            id: Ulid::random(),
            email: EmailAddress::fromString(self::EMAIL),
            hashedPassword: $this->hasher->hash(self::PASSWORD),
            status: $status,
            failedLoginAttempts: $failedLoginAttempts,
        );

        $this->repository->save($user);

        return $user;
    }

    private function query(string $password = self::PASSWORD): AuthenticateUserQuery
    {
        return new AuthenticateUserQuery(self::EMAIL, $password);
    }

    public function test_it_returns_an_auth_token_dto_on_success(): void
    {
        $user = $this->givenUser();

        $dto = ($this->handler)($this->query());

        $this->assertInstanceOf(AuthTokenDto::class, $dto);
        $this->assertSame('Bearer', $dto->tokenType);
        $this->assertSame($user->id->getValue(), $dto->userId);
        $this->assertNotSame('', $dto->accessToken);
    }

    public function test_it_records_the_authenticated_event_on_success(): void
    {
        $user = $this->givenUser();

        ($this->handler)($this->query());

        $events = $user->pullEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserAuthenticated::class, $events[0]);
    }

    public function test_an_unknown_email_reports_generic_invalid_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Email atau password yang Anda masukkan salah.');

        ($this->handler)(new AuthenticateUserQuery('nobody@example.com', self::PASSWORD));
    }

    public function test_a_wrong_password_reports_generic_invalid_credentials(): void
    {
        $this->givenUser();

        $this->expectException(InvalidCredentialsException::class);

        ($this->handler)($this->query('wrong-password'));
    }

    public function test_an_unknown_email_and_a_wrong_password_are_indistinguishable(): void
    {
        $this->givenUser();

        $unknownEmail = null;
        $wrongPassword = null;

        try {
            ($this->handler)(new AuthenticateUserQuery('nobody@example.com', self::PASSWORD));
        } catch (InvalidCredentialsException $exception) {
            $unknownEmail = $exception;
        }

        try {
            ($this->handler)($this->query('wrong-password'));
        } catch (InvalidCredentialsException $exception) {
            $wrongPassword = $exception;
        }

        $this->assertNotNull($unknownEmail);
        $this->assertNotNull($wrongPassword);
        $this->assertSame($unknownEmail->getMessage(), $wrongPassword->getMessage());
        $this->assertSame($unknownEmail->getCode(), $wrongPassword->getCode());
    }

    public function test_a_failed_attempt_is_persisted_so_the_counter_survives(): void
    {
        $this->givenUser();

        try {
            ($this->handler)($this->query('wrong-password'));
        } catch (InvalidCredentialsException) {
            // expected
        }

        $reloaded = $this->repository->findByEmail(EmailAddress::fromString(self::EMAIL));
        $this->assertNotNull($reloaded);
        $this->assertSame(1, $reloaded->failedLoginAttempts());
    }

    public function test_repeated_failures_eventually_persist_the_suspension(): void
    {
        $this->givenUser(failedLoginAttempts: 4);

        try {
            ($this->handler)($this->query('wrong-password'));
        } catch (InvalidCredentialsException) {
            // expected
        }

        $reloaded = $this->repository->findByEmail(EmailAddress::fromString(self::EMAIL));
        $this->assertNotNull($reloaded);
        $this->assertSame(AccountStatus::SUSPENDED, $reloaded->status());
    }

    public function test_a_successful_login_persists_the_counter_reset(): void
    {
        $this->givenUser(failedLoginAttempts: 3);

        ($this->handler)($this->query());

        $reloaded = $this->repository->findByEmail(EmailAddress::fromString(self::EMAIL));
        $this->assertNotNull($reloaded);
        $this->assertSame(0, $reloaded->failedLoginAttempts());
    }

    public function test_a_suspended_account_is_refused_even_with_the_right_password(): void
    {
        $this->givenUser(status: AccountStatus::SUSPENDED);

        $this->expectException(AccountSuspendedException::class);

        ($this->handler)($this->query());
    }

    public function test_an_unverified_account_is_refused_even_with_the_right_password(): void
    {
        $this->givenUser(status: AccountStatus::UNVERIFIED);

        $this->expectException(AccountUnverifiedException::class);

        ($this->handler)($this->query());
    }

    public function test_it_matches_the_email_case_insensitively(): void
    {
        $this->givenUser();

        $dto = ($this->handler)(new AuthenticateUserQuery('User@Example.COM', self::PASSWORD));

        $this->assertInstanceOf(AuthTokenDto::class, $dto);
    }

    public function test_it_does_not_create_duplicate_records_while_authenticating(): void
    {
        $this->givenUser();

        ($this->handler)($this->query());

        $this->assertSame(1, $this->repository->count());
    }
}
