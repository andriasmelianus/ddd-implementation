<?php

declare(strict_types=1);

namespace Src\IAM\UI\Controller;

use Illuminate\Http\JsonResponse;
use Src\IAM\Application\DTO\AuthTokenDto;
use Src\IAM\Application\Query\AuthenticateUserQuery;
use Src\IAM\UI\Request\LoginRequest;
use Src\IAM\UI\Resource\LoginRes;
use Src\Shared\Framework\Infrastructure\Bus\QueryBus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Class LoginAction
 *
 * Single-action controller for `POST /api/v1/auth/login`.
 *
 * THIN BY CONTRACT (Critical Rule #6): this class contains ZERO business logic.
 * Every authentication rule (email format, account status, password match,
 * failed-attempt counting, auto-suspension) lives inside the Domain Layer
 * (`User::authenticate()`) and is orchestrated by `AuthenticateUserHandler`.
 *
 * Responsibilities limited to:
 *  1. Take the already-validated, strongly-typed DTO from the FormRequest.
 *  2. Dispatch the use case through the QueryBus.
 *  3. Translate the Application DTO into the public `LoginRes` resource.
 *
 * Domain exceptions (InvalidCredentialsException, AccountSuspendedException,
 * AccountUnverifiedException, InvalidEmailException) intentionally bubble up
 * and are rendered by the global exception handler — the controller never
 * inspects nor branches on them.
 */
final class LoginAction
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $dto = $request->getDto();

        /** @var AuthTokenDto $authToken */
        $authToken = $this->queryBus->query(new AuthenticateUserQuery(
            email: $dto->email,
            plainPassword: $dto->password,
        ));

        return response()->json(LoginRes::fromDto($authToken), HttpStatus::HTTP_OK);
    }
}
