<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Port;

use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Domain\Entity\User;

interface TokenGeneratorInterface
{
    public function generateTokenFor(User $user): AuthTokenDto;
}

