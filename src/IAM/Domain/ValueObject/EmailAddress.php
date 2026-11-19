<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObject;

use Src\IAM\Domain\Exception\InvalidEmailException;
use Stringable;

final readonly class EmailAddress implements Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (! filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException(sprintf('Format email tidak valid: "%s"', $value));
        }

        $this->value = strtolower($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

