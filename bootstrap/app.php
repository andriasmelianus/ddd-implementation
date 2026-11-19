<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\IAM\Domain\Exceptions\AccountSuspendedException;
use Src\IAM\Domain\Exceptions\AccountUnverifiedException;
use Src\IAM\Domain\Exceptions\InvalidCredentialsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidCredentialsException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => $exception->getMessage()], 401);
        });

        $exceptions->render(function (AccountSuspendedException|AccountUnverifiedException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => $exception->getMessage()], 403);
        });
    })->create();
