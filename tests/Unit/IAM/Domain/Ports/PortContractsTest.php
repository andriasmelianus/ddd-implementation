<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Domain\Ports;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Ports\PasswordHasherInterface;
use Src\IAM\Domain\Ports\TokenGeneratorInterface;
use Src\IAM\Domain\Ports\UserRepositoryInterface;
use Src\IAM\Domain\ValueObjects\EmailAddress;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;
use Tests\Unit\IAM\Support\FakePasswordHasher;
use Tests\Unit\IAM\Support\InMemoryUserRepository;
use Tests\Unit\IAM\Support\StubTokenGenerator;

class PortContractsTest extends TestCase
{
    private function user(string $email = 'user@example.com'): User
    {
        return User::create(
            id: Ulid::random(),
            email: EmailAddress::fromString($email),
            hashedPassword: (new FakePasswordHasher)->hash('secret'),
        );
    }

    public function test_the_three_ports_are_interfaces(): void
    {
        $this->assertTrue(interface_exists(UserRepositoryInterface::class));
        $this->assertTrue(interface_exists(PasswordHasherInterface::class));
        $this->assertTrue(interface_exists(TokenGeneratorInterface::class));
    }

    public function test_save_returns_void_per_strict_cqrs(): void
    {
        $returnType = (new ReflectionMethod(UserRepositoryInterface::class, 'save'))->getReturnType();

        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertSame('void', $returnType->getName());
    }

    public function test_find_by_email_is_nullable_and_takes_a_value_object(): void
    {
        $method = new ReflectionMethod(UserRepositoryInterface::class, 'findByEmail');

        $returnType = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertTrue($returnType->allowsNull());
        $this->assertSame(User::class, $returnType->getName());

        $parameterType = $method->getParameters()[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $parameterType);
        $this->assertSame(EmailAddress::class, $parameterType->getName());
    }

    public function test_the_repository_has_no_read_model_projection_methods(): void
    {
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new \ReflectionClass(UserRepositoryInterface::class))->getMethods(),
        );

        sort($methods);
        $this->assertSame(['findByEmail', 'save'], $methods);
    }

    public function test_an_in_memory_repository_can_satisfy_the_contract(): void
    {
        $repository = new InMemoryUserRepository;
        $user = $this->user();

        $this->assertNull($repository->findByEmail($user->email));

        $repository->save($user);

        $found = $repository->findByEmail(EmailAddress::fromString('user@example.com'));
        $this->assertSame($user, $found);
    }

    public function test_the_repository_matches_email_case_insensitively(): void
    {
        $repository = new InMemoryUserRepository;
        $repository->save($this->user('User@Example.com'));

        $this->assertNotNull($repository->findByEmail(EmailAddress::fromString('user@example.com')));
    }

    public function test_saving_twice_updates_rather_than_duplicates(): void
    {
        $repository = new InMemoryUserRepository;
        $user = $this->user();

        $repository->save($user);
        $repository->save($user);

        $this->assertSame(1, $repository->count());
    }

    public function test_a_token_generator_can_satisfy_the_contract(): void
    {
        $token = (new StubTokenGenerator)->generateTokenFor($this->user());

        $this->assertNotSame('', $token);
    }

    public function test_a_generated_token_does_not_leak_the_password_hash(): void
    {
        $user = $this->user();

        $token = (new StubTokenGenerator)->generateTokenFor($user);

        $this->assertStringNotContainsString($user->hashedPassword(), $token);
    }
}
