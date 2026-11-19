<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginAction;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', LoginAction::class);
});
