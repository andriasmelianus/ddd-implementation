<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

use Src\Shared\Common\Exceptions\InvalidEmailException;
use Src\Shared\Common\ValueObjects\BaseEmail;

/**
 * Class EmailAddress
 *
 * Immutable Value Object representing the email address of an identity
 * within the IAM bounded context.
 *
 * Invariants are inherited from {@see BaseEmail}:
 * - value MUST NOT be empty (after trimming).
 * - value MUST NOT exceed 255 characters.
 * - value MUST be a syntactically valid email address.
 * - domain part is normalised to lowercase, local part preserved verbatim.
 *
 * Bersifat `final` — mencegah subclassing yang dapat merusak invariants.
 */
final readonly class EmailAddress extends BaseEmail
{
    /**
     * Named constructor for a more expressive call-site.
     *
     * @param  string  $value  Raw email string.
     *
     * @throws InvalidEmailException When the value is empty, too long, or invalid.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
