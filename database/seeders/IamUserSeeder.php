<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\IAM\Domain\ValueObjects\AccountStatus;

/**
 * Seeds one deterministic user for every IAM account status.
 *
 * All accounts use the development-only password `12345`.
 */
class IamUserSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, account_status: AccountStatus, failed_login_attempts: int}>
     */
    private const USERS = [
        'unverified@example.com' => [
            'name' => 'Unverified User',
            'account_status' => AccountStatus::UNVERIFIED,
            'failed_login_attempts' => 0,
        ],
        'active@example.com' => [
            'name' => 'Active User',
            'account_status' => AccountStatus::ACTIVE,
            'failed_login_attempts' => 0,
        ],
        'suspended@example.com' => [
            'name' => 'Suspended User',
            'account_status' => AccountStatus::SUSPENDED,
            'failed_login_attempts' => 5,
        ],
    ];

    public function run(): void
    {
        foreach (self::USERS as $email => $data) {
            $user = User::query()->firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $user->id = (string) Str::ulid();
            }

            $user->fill([
                'name' => $data['name'],
                'email_verified_at' => $data['account_status'] === AccountStatus::UNVERIFIED ? null : now(),
                'password' => Hash::make('12345'),
                'failed_login_attempts' => $data['failed_login_attempts'],
                // Migration saat ini menjadikan kolom ini NOT NULL. Nilai ini
                // hanya memenuhi constraint persistence; domain User belum
                // memodelkan waktu login terakhir.
                'last_login_at' => now(),
                'account_status' => $data['account_status']->value,
            ]);

            $user->save();
        }
    }
}
