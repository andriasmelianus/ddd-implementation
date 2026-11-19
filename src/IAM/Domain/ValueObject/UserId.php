<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObject;

use Src\Shared\Framework\Domain\ValueObjects\Ulid;
use Stringable;

final class UserId extends Ulid implements Stringable
{
    public static function random(): static
    {
        return parent::random();
    }

    public static function fromString(string $ulid): static
    {
        return parent::fromString($ulid);
    }
}

