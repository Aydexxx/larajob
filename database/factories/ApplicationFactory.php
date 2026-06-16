<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'job_id'       => Job::factory(),
            'user_id'      => User::factory()->candidate(),
            'cover_letter' => implode("\n\n", fake()->paragraphs(3)),
            'resume_path'  => null,
            'status'       => fake()->randomElement(['pending', 'pending', 'reviewed', 'accepted', 'rejected']),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'reviewed']);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'accepted']);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'rejected']);
    }
}
