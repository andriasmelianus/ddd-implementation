<?php

declare(strict_types=1);

namespace Src\IAM\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Src\IAM\Domain\Entity\User;

/**
 * Eloquent Model `UserModel` — **murni mekanisme penyimpanan**.
 *
 * ⚠️ Model ini adalah detail Infrastructure Layer dan **tidak boleh** bocor ke
 * Domain maupun Application Layer. Tidak ada satu pun aturan bisnis di sini:
 * tanpa accessor bisnis, tanpa scope berisi kebijakan domain, tanpa event model.
 * Seluruh logika autentikasi berada di Aggregate Root
 * {@see User}.
 *
 * Pemetaan ke tabel `users` yang sudah ada pada migrasi proyek.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $account_status
 * @property int $failed_login_attempts
 * @property string|null $last_login_at
 * @property string|null $email_verified_at
 */
final class UserModel extends Model
{
    use HasUlids;

    /**
     * Tabel persistensi.
     */
    protected $table = 'users';

    /**
     * Primary key bertipe ULID (string), bukan auto-increment.
     */
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
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

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'failed_login_attempts' => 'integer',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }
}
