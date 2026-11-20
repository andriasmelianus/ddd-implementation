<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginAction;
use Illuminate\Support\Facades\Route;
use Src\IAM\UI\Controller\LoginWithDeviceAction;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', LoginAction::class);
    Route::post('auth/login-device', LoginWithDeviceAction::class);
});
