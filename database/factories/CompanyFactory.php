<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    private static array $industries = [
        'Technologies', 'Systems', 'Solutions', 'Software', 'Digital',
        'Analytics', 'Cloud', 'Labs', 'Studio', 'Group',
    ];

    public function definition(): array
    {
        $base = fake()->unique()->company();
        $name = $base . ' ' . fake()->randomElement(self::$industries);
        $domain = Str::slug($base);

        return [
            'user_id'     => User::factory()->employer(),
            'name'        => $name,
            'slug'        => Str::slug($name) . '-' . fake()->unique()->numerify('####'),
            'logo'        => null,
            'description' => implode(' ', fake()->paragraphs(2)),
            'website'     => "https://www.{$domain}.com",
            'location'    => fake()->city() . ', ' . fake()->country(),
            'is_verified' => fake()->boolean(65),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }
}
