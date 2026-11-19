<?php

declare(strict_types=1);

namespace Src\IAM\Application\Command;

use Src\Shared\Framework\Application\Commands\CommandInterface;

final readonly class AuthenticateUserCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}

