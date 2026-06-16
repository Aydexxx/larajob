<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployerSeeder extends Seeder
{
    private static array $companyProfiles = [
        ['Apex Digital Solutions',       'apex-digital-solutions',       'San Francisco, CA, USA', 'https://www.apexdigital.com'],
        ['Meridian Cloud Technologies',  'meridian-cloud-technologies',  'New York, NY, USA',      'https://www.meridiancloud.com'],
        ['Cobalt Software Group',        'cobalt-software-group',        'London, UK',             'https://www.cobaltgroup.io'],
        ['Stratos Labs',                 'stratos-labs',                 'Berlin, Germany',        'https://www.stratoslabs.de'],
        ['Helix Data Systems',           'helix-data-systems',           'Toronto, Canada',        'https://www.helixdata.ca'],
        ['Luminary Studio',              'luminary-studio',              'Amsterdam, Netherlands', 'https://www.luminarystudio.nl'],
        ['Pinnacle Tech',                'pinnacle-tech',                'Sydney, Australia',      'https://www.pinnacletech.com.au'],
        ['Orion Analytics',              'orion-analytics',              'Singapore',              'https://www.orionanalytics.sg'],
    ];

    public function run(): void
    {
        foreach (self::$companyProfiles as $index => [$name, $slug, $location, $website]) {
            $n = $index + 1;

            $employer = User::updateOrCreate(
                ['email' => "employer{$n}@larajob.test"],
                [
                    'name'              => fake()->name(),
                    'role'              => 'employer',
                    'email_verified_at' => now(),
                    'password'          => Hash::make('password'),
                ]
            );

            $company = Company::updateOrCreate(
                ['slug' => $slug],
                [
                    'user_id'     => $employer->id,
                    'name'        => $name,
                    'description' => implode(' ', fake()->paragraphs(2)),
                    'website'     => $website,
                    'location'    => $location,
                    'is_verified' => $n <= 5,
                ]
            );

            // 3 guaranteed active jobs per company
            Job::factory(3)->active()->create(['company_id' => $company->id]);

            // 0–3 additional jobs with mixed statuses
            $extra = rand(0, 3);
            if ($extra > 0) {
                Job::factory($extra)->create(['company_id' => $company->id]);
            }
        }
    }
}
