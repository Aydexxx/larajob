<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@larajob.test'],
            [
                'name'              => 'Admin User',
                'role'              => 'admin',
                'email_verified_at' => now(),
                'password'          => Hash::make('password'),
            ]
        );
    }
}
