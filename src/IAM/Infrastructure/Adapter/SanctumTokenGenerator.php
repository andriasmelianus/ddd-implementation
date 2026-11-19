<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Adapter;

use Illuminate\Support\Str;
use Src\IAM\Domain\Entity\User;
use Src\IAM\Domain\Port\TokenGeneratorInterface;

/**
 * Adapter konkret {@see TokenGeneratorInterface}.
 *
 * Aturan Bisnis #5: dipakai setelah seluruh invariant login lolos.
 *
 * ℹ️ Catatan implementasi: paket `laravel/sanctum` belum terpasang pada proyek
 * ini, sehingga adapter menghasilkan *opaque bearer token* bergaya Sanctum
 * (`<id>|<plain-random>`). Karena Domain & Application Layer hanya bergantung
 * pada port abstrak, penggantian ke `createToken()` milik Sanctum nantinya
 * **tidak** memerlukan perubahan apa pun di luar kelas ini — inilah manfaat
 * utama Hexagonal Architecture.
 */
final readonly class SanctumTokenGenerator implements TokenGeneratorInterface
{
    /**
     * Skema token yang dikirim pada field `token_type`.
     */
    private const string TOKEN_TYPE = 'Bearer';

    /**
     * Panjang bagian acak token.
     */
    private const int TOKEN_RANDOM_LENGTH = 40;

    public function generateFor(User $user): string
    {
        return sprintf(
            '%s|%s',
            $user->id()->value(),
            Str::random(self::TOKEN_RANDOM_LENGTH),
        );
    }

    public function tokenType(): string
    {
        return self::TOKEN_TYPE;
    }
}
