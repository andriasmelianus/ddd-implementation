<?php

use App\Providers\AppServiceProvider;
use Src\IAM\Infrastructure\Providers\IamServiceProvider;
use Src\Shared\Framework\Infrastructure\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IamServiceProvider::class,
];
