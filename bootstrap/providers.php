<?php

use App\Providers\AppServiceProvider;
use Src\IAM\Infrastructure\Providers\IAMServiceProvider;

return [
    AppServiceProvider::class,
    IAMServiceProvider::class,
    Src\IAM\Infrastructure\Providers\IAMServiceProvider::class,
];
