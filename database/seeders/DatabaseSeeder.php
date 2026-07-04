<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * EmployerSeeder, CandidateSeeder and ApplicationSeeder generate
     * lorem-ipsum companies, jobs, bios and cover letters for local demoing
     * — never acceptable content on a live site with real users. They only
     * run in local/testing; AdminSeeder (needed everywhere to have an admin
     * account at all) always runs, with production-safe credentials — see
     * AdminSeeder.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(
                'Demo data (companies, jobs, candidates, applications) is only seeded in '.
                'local/testing environments — skipped in "'.app()->environment().'".'
            );

            return;
        }

        $this->call([
            EmployerSeeder::class,
            CandidateSeeder::class,
            ApplicationSeeder::class,
        ]);
    }
}
