<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\IAM\Domain\Exception\AccountSuspendedException;
use Src\IAM\Domain\Exception\AccountUnverifiedException;
use Src\IAM\Domain\Exception\InvalidCredentialsException;
use Src\Shared\Common\Exceptions\InvalidEmailException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        |----------------------------------------------------------------------
        | Domain Exception -> HTTP Status translation
        |----------------------------------------------------------------------
        |
        | Mapping domain failures to HTTP status codes is a *presentation*
        | concern, so it lives here and NOT inside the Action/Handler.
        | This keeps Actions thin (Critical Rule #6) and the Domain Layer
        | completely free of HTTP knowledge.
        |
        */
        $exceptions->render(function (InvalidEmailException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(
                    ['message' => $e->getMessage(), 'errors' => ['email' => [$e->getMessage()]]],
                    HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
                )
                : null;
        });

        $exceptions->render(function (InvalidCredentialsException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], HttpStatus::HTTP_UNAUTHORIZED)
                : null;
        });

        $exceptions->render(function (AccountSuspendedException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], HttpStatus::HTTP_FORBIDDEN)
                : null;
        });

        $exceptions->render(function (AccountUnverifiedException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], HttpStatus::HTTP_FORBIDDEN)
                : null;
        });
    })->create();
