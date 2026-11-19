<?php

declare(strict_types=1);

namespace Src\IAM\Application\DTO;

use JsonSerializable;
use Src\IAM\Domain\Ports\TokenGeneratorInterface;

/**
 * Class AuthTokenDto
 *
 * DTO immutable yang membawa hasil autentikasi berhasil dari lapisan
 * Application ke pemanggilnya (Action/Controller).
 *
 * DTO ini sengaja hanya memuat data yang aman dikirim ke client — TIDAK
 * memuat hash password, status akun, maupun entitas domain. Dengan begitu
 * model domain tidak bocor ke lapisan presentasi.
 */
final readonly class AuthTokenDto implements JsonSerializable
{
    /**
     * Tipe token default sesuai skema `Authorization: Bearer <token>`.
     */
    public const DEFAULT_TOKEN_TYPE = 'Bearer';

    /**
     * @param  string  $accessToken  Token akses hasil {@see TokenGeneratorInterface}.
     * @param  string  $tokenType  Skema token (default `Bearer`).
     * @param  string  $userId  ULID user yang terautentikasi.
     */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public string $userId,
    ) {}

    /**
     * Named constructor dengan `tokenType` default `Bearer`.
     */
    public static function bearer(string $accessToken, string $userId): self
    {
        return new self(
            accessToken: $accessToken,
            tokenType: self::DEFAULT_TOKEN_TYPE,
            userId: $userId,
        );
    }

    /**
     * @return array{access_token: string, token_type: string, user_id: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'user_id' => $this->userId,
        ];
    }
}
