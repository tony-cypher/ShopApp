<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@mlc.test'],
            [
                'name' => 'Demo Shopper',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'verification_token' => null,
            ],
        );
    }
}
