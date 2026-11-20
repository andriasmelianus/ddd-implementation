<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Src\IAM\Infrastructure\Persistence\UserModel;
use Symfony\Component\Uid\Ulid;

/**
 * Seeds one account per login scenario so `POST /api/v1/auth/login`
 * can be exercised manually from Postman / curl.
 *
 * Every account uses the same password: secret123
 */
class IamUserSeeder extends Seeder
{
    use WithoutModelEvents;

    private const string PASSWORD = 'secret123';

    public function run(): void
    {
        $accounts = [
            // 200 OK — happy path.
            [
                'email' => 'active@example.com',
                'name' => 'Active User',
                'account_status' => 'active',
                'email_verified_at' => now(),
                'failed_login_attempts' => 0,
            ],
            // 403 — 'Silakan verifikasi email Anda terlebih dahulu.'
            [
                'email' => 'unverified@example.com',
                'name' => 'Unverified User',
                'account_status' => 'unverified',
                'email_verified_at' => null,
                'failed_login_attempts' => 0,
            ],
            // 403 — 'Akun Anda sedang ditangguhkan.'
            [
                'email' => 'suspended@example.com',
                'name' => 'Suspended User',
                'account_status' => 'suspended',
                'email_verified_at' => now(),
                'failed_login_attempts' => 5,
            ],
            // Active but 1 wrong password away from auto-suspension,
            // handy for demoing Business Rule #4.
            [
                'email' => 'almost@example.com',
                'name' => 'Almost Locked User',
                'account_status' => 'active',
                'email_verified_at' => now(),
                'failed_login_attempts' => 4,
            ],
        ];

        foreach ($accounts as $account) {
            UserModel::query()->updateOrCreate(
                ['email' => $account['email']],
                $account + [
                    'id' => (string) new Ulid,
                    'password' => Hash::make(self::PASSWORD),
                    'last_login_at' => null,
                ],
            );
        }
    }
}
