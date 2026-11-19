<?php

declare(strict_types=1);

namespace Src\IAM\Application\DTO;

use JsonSerializable;

final readonly class AuthTokenDto implements JsonSerializable
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public string $userId,
    ) {}

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

