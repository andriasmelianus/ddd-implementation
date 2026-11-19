<?php

declare(strict_types=1);

namespace Src\IAM\UI\Controller;

use Illuminate\Http\JsonResponse;
use Src\IAM\Application\Command\AuthenticateUserCommandHandler;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\IAM\Domain\Exception\InvalidEmailException;
use Src\IAM\UI\Request\LoginRequest;

final readonly class LoginAction
{
    public function __construct(
        private AuthenticateUserCommandHandler $handler,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $tokenDto = ($this->handler)($request->getDto());

            return response()->json($tokenDto, 200);
        } catch (InvalidCredentialsException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (AccountSuspendedException|AccountUnverifiedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidEmailException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
