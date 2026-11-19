<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IAM\UI\Controller\LoginAction;

/*
|--------------------------------------------------------------------------
| API Routes — IAM (Identity & Access Management) Bounded Context
|--------------------------------------------------------------------------
|
| Routes are grouped per bounded context. Each endpoint points to a single
| invokable Action (Critical Rule #6: Thin Actions), never to a fat
| multi-method controller.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', LoginAction::class)->name('api.v1.auth.login');
    });
});
