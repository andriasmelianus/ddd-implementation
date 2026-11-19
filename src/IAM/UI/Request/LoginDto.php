<?php

declare(strict_types=1);

namespace Src\IAM\UI\Request;

use Src\IAM\Domain\ValueObjects\EmailAddress;

/**
 * Input DTO untuk use case login.
 *
 * 🚨 Critical Rule #7 (Typed Requests): FormRequest hanya memvalidasi
 * **format**, lalu memetakan input menjadi DTO bertipe kuat seperti ini.
 * Perhatikan bahwa `email` sudah berupa Value Object {@see EmailAddress} —
 * bukan `string` mentah — sehingga Aturan Bisnis #1 (validasi sintaksis email
 * oleh Value Object) tetap ditegakkan oleh Domain Layer.
 *
 * Password tetap `string` karena plaintext hanya objek sementara yang langsung
 * diteruskan ke port hasher dan tidak pernah dipersistensi.
 */
final readonly class LoginDto
{
    public function __construct(
        public EmailAddress $email,
        public string $password,
    ) {}
}
