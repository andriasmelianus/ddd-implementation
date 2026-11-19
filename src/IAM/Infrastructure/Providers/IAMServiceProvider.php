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

final class IAMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(PasswordHasherInterface::class, BcryptPasswordHasher::class);
        $this->app->bind(TokenGeneratorInterface::class, SanctumTokenGenerator::class);
    }

    public function boot(): void
    {
        //
    }
}
