<?php

declare(strict_types=1);

namespace Src\IAM\Domain\Exception;

use DomainException;

/**
 * Dilempar ketika hash password yang direkonstitusi dari persistensi tidak valid
 * (mis. kolom kosong akibat data korup).
 *
 * Exception ini menandakan pelanggaran invariant internal, bukan kesalahan input
 * pengguna, sehingga **tidak** diekspos ke klien HTTP.
 */
final class InvalidHashedPasswordException extends DomainException
{
    public static function empty(): self
    {
        return new self('Hashed password cannot be empty.');
    }
}
