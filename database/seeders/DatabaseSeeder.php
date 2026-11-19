<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's deterministic development accounts.
     */
    public function run(): void
    {
        $this->call(IamUserSeeder::class);
    }
}
