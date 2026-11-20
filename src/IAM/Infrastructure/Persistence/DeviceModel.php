<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent persistence record for a device known to IAM.
 *
 * This model intentionally contains no domain behaviour; mapping is performed
 * by EloquentDeviceRepository.
 *
 * @property string $id
 * @property string $user_id
 * @property string $status
 * @property \Carbon\CarbonImmutable|null $last_used_at
 * @property string|null $ip_address
 */
final class DeviceModel extends Model
{
    protected $table = 'devices';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'user_id',
        'status',
        'last_used_at',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'immutable_datetime',
        ];
    }
}
