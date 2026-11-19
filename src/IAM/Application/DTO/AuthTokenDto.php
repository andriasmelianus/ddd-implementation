<?php

declare(strict_types=1);

namespace Src\IAM\Application\DTO;

use Src\IAM\Domain\Port\TokenGeneratorInterface;
use Src\IAM\Domain\ValueObjects\UserId;

/**
 * Output DTO hasil autentikasi yang berhasil.
 *
 * Aturan Bisnis #5: dikembalikan setelah seluruh pengecekan lolos, counter
 * percobaan gagal direset, `last_login_at` diperbarui, dan token dibuat.
 *
 * DTO ini adalah objek transfer **internal** Application Layer. Konversi ke
 * payload HTTP (`access_token`, `token_type`, `user_id`) adalah tanggung jawab
 * Custom Resource pada Presentation Layer, bukan DTO ini.
 */
final readonly class AuthTokenDto
{
    /**
     * @param  string  $accessToken  Access token hasil {@see TokenGeneratorInterface}.
     * @param  string  $tokenType  Skema token, mis. `Bearer`.
     * @param  UserId  $userId  Identitas pengguna yang berhasil login.
     */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public UserId $userId,
    ) {}

    /**
     * Representasi primitif DTO.
     *
     * @return array{access_token: string, token_type: string, user_id: string}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'user_id' => $this->userId->value(),
        ];
    }
}
