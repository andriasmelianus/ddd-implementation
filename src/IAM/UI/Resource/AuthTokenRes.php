<?php

declare(strict_types=1);

namespace Src\IAM\UI\Resource;

use JsonSerializable;
use Src\IAM\Application\DTO\AuthTokenDto;

final readonly class AuthTokenRes implements JsonSerializable
{
    public function __construct(private AuthTokenDto $token) {}

    /**
     * @return array{access_token: string, token_type: string, user_id: string}
     */
    public function jsonSerialize(): array
    {
        return $this->token->jsonSerialize();
    }
}
