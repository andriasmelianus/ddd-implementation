<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Port;

use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\ValueObject\EmailAddress;
use Src\IAM\Domain\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function findByEmail(EmailAddress $email): ?User;

    public function findById(UserId $id): ?User;

    public function save(User $user): void;
}

