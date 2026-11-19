<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\IAM\Domain\Ports\PasswordHasherInterface;
use Src\IAM\Domain\Ports\TokenGeneratorInterface;
use Src\IAM\Domain\Ports\UserRepositoryInterface;
use Src\IAM\Infrastructure\Adapter\BcryptPasswordHasher;
use Src\IAM\Infrastructure\Adapter\LocalTokenGenerator;
use Src\IAM\Infrastructure\Persistence\EloquentUserRepository;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBus;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register infrastructure adapters for Domain/Application ports.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(PasswordHasherInterface::class, BcryptPasswordHasher::class);
        $this->app->bind(TokenGeneratorInterface::class, LocalTokenGenerator::class);
        $this->app->singleton(QueryBusInterface::class, QueryBus::class);
    }

    public function boot(): void {}
}
