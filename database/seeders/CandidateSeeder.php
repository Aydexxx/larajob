<?php

namespace Database\Seeders;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $candidate = User::updateOrCreate(
                ['email' => "candidate{$i}@larajob.test"],
                [
                    'name'              => fake()->name(),
                    'role'              => 'candidate',
                    'email_verified_at' => now(),
                    'password'          => Hash::make('password'),
                ]
            );

            if (! $candidate->candidateProfile()->exists()) {
                CandidateProfile::factory()->create(['user_id' => $candidate->id]);
            }
        }
    }
}
