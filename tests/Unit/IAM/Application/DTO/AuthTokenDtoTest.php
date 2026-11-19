<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Application\DTO;

use JsonSerializable;
use PHPUnit\Framework\TestCase;
use Src\IAM\Application\DTO\AuthTokenDto;

class AuthTokenDtoTest extends TestCase
{
    public function test_it_exposes_the_three_fields(): void
    {
        $dto = new AuthTokenDto('token-value', 'Bearer', '01HQ');

        $this->assertSame('token-value', $dto->accessToken);
        $this->assertSame('Bearer', $dto->tokenType);
        $this->assertSame('01HQ', $dto->userId);
    }

    public function test_the_bearer_factory_applies_the_default_token_type(): void
    {
        $dto = AuthTokenDto::bearer('token-value', '01HQ');

        $this->assertSame('Bearer', $dto->tokenType);
    }

    public function test_it_serialises_to_snake_case_json(): void
    {
        $dto = AuthTokenDto::bearer('token-value', '01HQ');

        $this->assertInstanceOf(JsonSerializable::class, $dto);
        $this->assertSame(
            ['access_token' => 'token-value', 'token_type' => 'Bearer', 'user_id' => '01HQ'],
            $dto->jsonSerialize(),
        );
    }

    public function test_it_encodes_to_json_without_leaking_extra_fields(): void
    {
        $encoded = json_encode(AuthTokenDto::bearer('token-value', '01HQ'), JSON_THROW_ON_ERROR);

        $this->assertSame(
            '{"access_token":"token-value","token_type":"Bearer","user_id":"01HQ"}',
            $encoded,
        );
    }
}
