<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds demo employers, companies and job listings for local/testing.
 *
 * Job content comes from the hand-written catalog in
 * database/seeders/data/job_roles.php — realistic, role-specific
 * descriptions and requirements, each with an explicit structured skills
 * list. The skills list is appended to the requirements verbatim so the
 * deterministic matcher (MatchService) finds genuine overlap with candidate
 * profiles and the "X of N skills matched" counter is meaningful.
 *
 * This affects seeded/demo listings only. Real employer-submitted jobs go
 * through the "post a job" flow (JobController) and are untouched by this.
 */
class EmployerSeeder extends Seeder
{
    /** @var array<int, array{0:string,1:string,2:string,3:string,4:string}> */
    private static array $companyProfiles = [
        ['Apex Digital Solutions',       'apex-digital-solutions',       'San Francisco, CA, USA', 'https://www.apexdigital.com',
            'Apex Digital Solutions builds custom software and digital products for fast-growing companies. Our engineers work across web, mobile and cloud to ship reliable products that scale with our clients.'],
        ['Meridian Cloud Technologies',  'meridian-cloud-technologies',  'New York, NY, USA',      'https://www.meridiancloud.com',
            'Meridian Cloud Technologies helps enterprises move to and thrive in the cloud. We design, build and operate resilient cloud platforms and the data pipelines that run on top of them.'],
        ['Cobalt Software Group',        'cobalt-software-group',        'London, UK',             'https://www.cobaltgroup.io',
            'Cobalt Software Group is a product studio building B2B SaaS tools used by teams around the world. We pair strong engineering with thoughtful design to solve unglamorous problems well.'],
        ['Stratos Labs',                 'stratos-labs',                 'Berlin, Germany',        'https://www.stratoslabs.de',
            'Stratos Labs is an R&D-driven company applying machine learning and modern data infrastructure to real-world products. We turn research into software people actually use.'],
        ['Helix Data Systems',           'helix-data-systems',           'Toronto, Canada',        'https://www.helixdata.ca',
            'Helix Data Systems builds the data platforms that power analytics and decision-making for data-intensive businesses. We make trustworthy data available to everyone who needs it.'],
        ['Luminary Studio',              'luminary-studio',              'Amsterdam, Netherlands', 'https://www.luminarystudio.nl',
            'Luminary Studio is a digital product and design studio crafting web and mobile experiences for ambitious brands. We care as much about how things feel as how they work.'],
        ['Pinnacle Tech',                'pinnacle-tech',                'Sydney, Australia',      'https://www.pinnacletech.com.au',
            'Pinnacle Tech develops mobile and web applications for startups and established companies across Asia-Pacific. We help teams get from idea to launch and keep improving from there.'],
        ['Orion Analytics',              'orion-analytics',              'Singapore',              'https://www.orionanalytics.sg',
            'Orion Analytics delivers analytics and machine learning solutions that help companies understand their customers and operate smarter. We combine data engineering, modelling and clear reporting.'],
    ];

    public function run(): void
    {
        /** @var array<int, array{title:string,skills:array<int,string>,description:string,requirements:string}> $roles */
        $roles = require database_path('seeders/data/job_roles.php');

        // Give each company its own contiguous slice of the catalog so its
        // listings feel coherent and companies don't all advertise the same
        // three roles. The slice wraps around the catalog.
        $perCompany = 4;

        foreach (self::$companyProfiles as $index => [$name, $slug, $location, $website, $about]) {
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
                    'description' => $about,
                    'website'     => $website,
                    'location'    => $location,
                    'is_verified' => $n <= 5,
                ]
            );

            $this->seedJobsForCompany($company, $roles, $index * $perCompany, $perCompany);
        }
    }

    /**
     * Create a company's listings from a rotating window of the role catalog.
     * The first three are guaranteed active; any remainder gets a mixed
     * status so the demo also exercises closed/draft states.
     *
     * @param  array<int, array{title:string,skills:array<int,string>,description:string,requirements:string}>  $roles
     */
    private function seedJobsForCompany(Company $company, array $roles, int $offset, int $count): void
    {
        $total = count($roles);

        for ($i = 0; $i < $count; $i++) {
            $role = $roles[($offset + $i) % $total];

            // First three listings are active; the rest vary so the demo also
            // shows closed and draft states.
            $status = $i < 3
                ? 'active'
                : fake()->randomElement(['active', 'closed', 'draft']);

            Job::factory()
                ->state($this->jobAttributes($role, $company, $status))
                ->create(['company_id' => $company->id]);
        }
    }

    /**
     * Build the attributes for one seeded listing from a catalog role.
     * The structured skills list is appended to the requirements verbatim so
     * the matcher scans it and the matched-skills counter is meaningful.
     *
     * @param  array{title:string,skills:array<int,string>,description:string,requirements:string}  $role
     * @return array<string, mixed>
     */
    private function jobAttributes(array $role, Company $company, string $status): array
    {
        $isRemote = fake()->boolean(35);

        $requirements = $this->normalize($role['requirements'])
            ."\n\nMust-have skills: ".implode(', ', $role['skills']).'.';

        return [
            'title'        => $role['title'],
            'slug'         => Str::slug($role['title']).'-'.fake()->unique()->numerify('#####'),
            'description'  => $this->normalize($role['description']),
            'requirements' => $requirements,
            'location'     => $isRemote ? 'Remote' : $company->location,
            'is_remote'    => $isRemote,
            'status'       => $status,
            'expires_at'   => $status === 'active'
                ? fake()->dateTimeBetween('+3 weeks', '+5 months')
                : null,
        ];
    }

    /**
     * Collapse the leading indentation that heredocs in the catalog carry, so
     * stored copy is clean. Paragraph breaks (blank lines) are preserved.
     */
    private function normalize(string $text): string
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];

        return collect($paragraphs)
            ->map(fn (string $p): string => trim(preg_replace('/\s+/', ' ', $p) ?? ''))
            ->filter()
            ->implode("\n\n");
    }
}
