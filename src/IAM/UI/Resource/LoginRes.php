<?php

declare(strict_types=1);

namespace Src\IAM\UI\Resource;

use JsonSerializable;
use Src\IAM\Application\DTO\AuthTokenDto;

/**
 * Class LoginRes
 *
 * Custom API Resource for the login endpoint (Critical Rule #6).
 *
 * This class is the ONLY place that decides the public JSON shape of
 * `POST /api/v1/auth/login`. It implements \JsonSerializable directly
 * (NOT Laravel's JsonResource) so the presentation contract stays explicit,
 * typed and free of framework magic.
 *
 * Contract produced:
 * {
 *   "access_token": "<opaque token>",
 *   "token_type": "Bearer",
 *   "user_id": "<ULID>"
 * }
 */
final readonly class LoginRes implements JsonSerializable
{
    public function __construct(
        private string $accessToken,
        private string $tokenType,
        private string $userId,
    ) {}

    /**
     * Convert the internal Application DTO into the public HTTP contract.
     *
     * The DTO never leaves the boundary: the UserId Value Object is flattened
     * to its primitive string representation here and only here.
     */
    public static function fromDto(AuthTokenDto $dto): self
    {
        return new self(
            accessToken: $dto->accessToken,
            tokenType: $dto->tokenType,
            userId: $dto->userId->value(),
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
