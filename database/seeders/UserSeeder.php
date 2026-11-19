<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Seeder;
use Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                // Jika model User belum pakai trait HasUlids, uncomment baris di bawah:
                'id' => (string) Str::ulid(),
                'name' => 'User',
                'email_verified_at' => now(),
                'password' => Hash::make('secret123'),
                'failed_login_attempts' => 0,
                'last_login_at' => now(),
                'account_status' => 'ACTIVE',
                'remember_token' => Str::random(10),
            ]
        );
    }
}
