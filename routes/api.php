<?php

use Illuminate\Support\Facades\Route;
use Src\IAM\UI\Controller\LoginAction;

Route::get('/', function () {
    return response()->json(['message' => 'Hallo dunia, ini API research DDD Implementation']);
});

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', LoginAction::class);
});
