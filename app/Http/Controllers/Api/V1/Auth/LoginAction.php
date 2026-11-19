<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Src\IAM\Application\DTO\AuthTokenDto;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;

final readonly class LoginAction
{
    public function __construct(private QueryBusInterface $queryBus) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        /** @var AuthTokenDto $token */
        $token = $this->queryBus->query($request->getDto());

        return response()->json($token);
    }
}
