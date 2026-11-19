<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Persistence;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class UserModel extends Authenticatable
{
    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'account_status',
        'failed_login_attempts',
        'last_login_at',
        'email_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_login_attempts' => 'integer',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }
}

