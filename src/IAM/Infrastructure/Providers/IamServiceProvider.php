<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Src\IAM\Domain\Port\PasswordHasherInterface;
use Src\IAM\Domain\Port\TokenGeneratorInterface;
use Src\IAM\Domain\Port\UserRepositoryInterface;
use Src\IAM\Infrastructure\Adapter\BcryptPasswordHasher;
use Src\IAM\Infrastructure\Adapter\EloquentUserRepository;
use Src\IAM\Infrastructure\Adapter\SanctumTokenGenerator;

/**
 * Class IamServiceProvider
 *
 * Composition root of the IAM bounded context.
 *
 * This is the ONLY place where Domain Ports (driven interfaces) are wired to
 * their Infrastructure Adapters. The Domain and Application layers depend
 * exclusively on the interfaces listed on the left-hand side, which is what
 * makes the hexagon swappable and unit-testable in isolation.
 */
final class IamServiceProvider extends ServiceProvider
{
    /**
     * Port => Adapter map (Hexagonal Architecture / Dependency Inversion).
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => EloquentUserRepository::class,
        PasswordHasherInterface::class => BcryptPasswordHasher::class,
        TokenGeneratorInterface::class => SanctumTokenGenerator::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $port => $adapter) {
            $this->app->bind($port, $adapter);
        }
    }
}
