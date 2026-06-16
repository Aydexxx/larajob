<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    private static array $titles = [
        'Software Engineer',
        'Senior Software Engineer',
        'Staff Software Engineer',
        'Frontend Developer',
        'Backend Developer',
        'Full Stack Developer',
        'iOS Developer',
        'Android Developer',
        'DevOps Engineer',
        'Site Reliability Engineer',
        'Cloud Architect',
        'Machine Learning Engineer',
        'Data Scientist',
        'Data Engineer',
        'Security Engineer',
        'QA Engineer',
        'Product Designer',
        'UX Designer',
        'UI/UX Designer',
        'Product Manager',
        'Engineering Manager',
        'Technical Lead',
        'Technical Writer',
        'Marketing Manager',
        'Growth Manager',
        'Customer Success Manager',
        'Business Analyst',
        'Scrum Master',
        'Sales Engineer',
        'Content Strategist',
    ];

    private static array $officeLocations = [
        'New York, NY, USA',
        'San Francisco, CA, USA',
        'Seattle, WA, USA',
        'Austin, TX, USA',
        'Boston, MA, USA',
        'Chicago, IL, USA',
        'Los Angeles, CA, USA',
        'London, UK',
        'Berlin, Germany',
        'Amsterdam, Netherlands',
        'Paris, France',
        'Dublin, Ireland',
        'Stockholm, Sweden',
        'Barcelona, Spain',
        'Lisbon, Portugal',
        'Toronto, Canada',
        'Vancouver, Canada',
        'Sydney, Australia',
        'Melbourne, Australia',
        'Singapore',
        'Tokyo, Japan',
        'Seoul, South Korea',
        'Tel Aviv, Israel',
        'Warsaw, Poland',
    ];

    public function definition(): array
    {
        $title    = fake()->randomElement(self::$titles);
        $isRemote = fake()->boolean(35);
        $location = $isRemote ? 'Remote' : fake()->randomElement(self::$officeLocations);
        $salaryMin = fake()->numberBetween(45, 140) * 1000;
        $salaryMax = $salaryMin + (fake()->numberBetween(10, 60) * 1000);

        return [
            'company_id'   => Company::factory(),
            'title'        => $title,
            'slug'         => Str::slug($title) . '-' . fake()->unique()->numerify('#####'),
            'description'  => implode("\n\n", fake()->paragraphs(3)),
            'requirements' => implode("\n\n", fake()->paragraphs(2)),
            'salary_min'   => $salaryMin,
            'salary_max'   => $salaryMax,
            'location'     => $location,
            'is_remote'    => $isRemote,
            'type'         => fake()->randomElement(['full-time', 'full-time', 'full-time', 'part-time', 'contract', 'internship']),
            'status'       => fake()->randomElement(['active', 'active', 'active', 'closed', 'draft']),
            'expires_at'   => fake()->optional(0.7)->dateTimeBetween('+2 weeks', '+6 months'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'active',
            'expires_at' => fake()->dateTimeBetween('+2 weeks', '+4 months'),
        ]);
    }

    public function remote(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_remote' => true,
            'location'  => 'Remote',
        ]);
    }
}
