<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence record for the IAM user aggregate.
 *
 * It is storage-only. Conversion to and from the domain aggregate belongs to
 * EloquentUserRepository.
 *
 * @property string $id
 * @property string $email
 * @property string $password
 * @property string $account_status
 * @property int $failed_login_attempts
 * @property \Carbon\CarbonImmutable|null $last_login_at
 */
class UserModel extends Model
{
    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'account_status',
        'failed_login_attempts',
        'last_login_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_login_attempts' => 'integer',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
