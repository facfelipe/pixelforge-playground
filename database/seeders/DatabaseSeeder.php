<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'user@test.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'credit_balance' => config('billing.starting_balance'),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'credit_balance' => config('billing.starting_balance'),
            ],
        );
    }
}
