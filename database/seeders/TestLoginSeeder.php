<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestLoginSeeder extends Seeder
{
    /**
     * Legt den Test-Login für das Admin-Panel an (idempotent).
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Admin', 'password' => 'password'],
        );
    }
}
