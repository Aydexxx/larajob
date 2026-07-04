<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the one admin account every environment needs. Unlike the demo
 * seeders (see DatabaseSeeder), this always runs — including in
 * production — but it never ships a guessable default password there.
 *
 * Credentials are configurable via ADMIN_EMAIL / ADMIN_PASSWORD. Outside
 * local/testing, if ADMIN_PASSWORD isn't set, a random password is
 * generated and printed once instead of falling back to the well-known
 * demo password "password".
 *
 * Only ever creates: if an admin with this email already exists, it is
 * left untouched, so re-running `db:seed` against a live database can
 * never reset a real admin's password.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');

        if (User::where('email', $email)->exists()) {
            return;
        }

        $isDemoEnvironment = app()->environment(['local', 'testing']);
        $envPassword = config('admin.password');
        $password = $envPassword ?: ($isDemoEnvironment ? 'password' : Str::random(24));

        User::create([
            'name'              => 'Admin User',
            'email'             => $email,
            'role'              => 'admin',
            'email_verified_at' => now(),
            'password'          => Hash::make($password),
        ]);

        if (blank($envPassword) && ! $isDemoEnvironment) {
            $this->command?->warn(
                "Generated admin password for {$email}: {$password}\n".
                'Save this now — it is not stored anywhere and will not be shown again.'
            );
        }
    }
}
