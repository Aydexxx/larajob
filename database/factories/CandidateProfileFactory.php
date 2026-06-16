<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    protected $model = CandidateProfile::class;

    private static array $headlines = [
        'Full Stack Developer with 5+ years of experience',
        'Senior Frontend Engineer specializing in React & TypeScript',
        'Backend Developer | PHP & Laravel Expert',
        'Data Scientist focused on ML and predictive analytics',
        'Product Designer passionate about user-centered design',
        'DevOps Engineer | Cloud Infrastructure & Automation',
        'iOS Developer with SwiftUI expertise',
        'Android Developer | Jetpack Compose & Kotlin',
        'Software Engineer | Open Source Contributor',
        'Senior Backend Engineer | REST APIs & Microservices',
        'QA Engineer | Test Automation & CI/CD Pipelines',
        'Engineering Manager | Team Builder & Agile Coach',
        'Data Engineer | Big Data Pipelines & ETL',
        'UX/UI Designer | Figma & Design Systems',
        'Junior Developer eager to grow in a product-driven team',
    ];

    private static array $skillSets = [
        ['PHP', 'Laravel', 'MySQL', 'Vue.js', 'Docker', 'REST APIs'],
        ['JavaScript', 'React', 'TypeScript', 'Node.js', 'GraphQL', 'PostgreSQL'],
        ['Python', 'Django', 'FastAPI', 'PostgreSQL', 'Redis', 'Celery'],
        ['Java', 'Spring Boot', 'Kubernetes', 'AWS', 'Kafka', 'Microservices'],
        ['Go', 'gRPC', 'Docker', 'Kubernetes', 'PostgreSQL', 'Prometheus'],
        ['Ruby', 'Rails', 'PostgreSQL', 'React', 'Sidekiq', 'Heroku'],
        ['Swift', 'SwiftUI', 'UIKit', 'CoreData', 'Combine', 'XCTest'],
        ['Kotlin', 'Android', 'Jetpack Compose', 'Firebase', 'Room', 'Coroutines'],
        ['Figma', 'Sketch', 'Adobe XD', 'Prototyping', 'User Research', 'Design Systems'],
        ['SQL', 'Apache Spark', 'Databricks', 'Airflow', 'dbt', 'Snowflake'],
        ['Terraform', 'AWS', 'Azure', 'CI/CD', 'Ansible', 'Prometheus'],
        ['Selenium', 'Cypress', 'Jest', 'PyTest', 'Playwright', 'k6'],
        ['Python', 'TensorFlow', 'PyTorch', 'Scikit-learn', 'Pandas', 'R'],
        ['C#', '.NET', 'Azure', 'SQL Server', 'Blazor', 'Entity Framework'],
        ['PHP', 'WordPress', 'WooCommerce', 'MySQL', 'CSS', 'JavaScript'],
    ];

    public function definition(): array
    {
        $skills = fake()->randomElement(self::$skillSets);

        return [
            'user_id'         => User::factory()->candidate(),
            'headline'        => fake()->randomElement(self::$headlines),
            'bio'             => implode(' ', fake()->paragraphs(2)),
            'skills'          => implode(', ', $skills),
            'experience_years' => fake()->numberBetween(0, 15),
            'phone'           => fake()->phoneNumber(),
            'location'        => fake()->city() . ', ' . fake()->country(),
            'linkedin_url'    => 'https://www.linkedin.com/in/' . fake()->userName(),
            'resume_path'     => null,
        ];
    }
}
