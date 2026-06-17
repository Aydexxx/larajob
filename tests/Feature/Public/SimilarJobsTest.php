<?php

namespace Tests\Feature\Public;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class SimilarJobsTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::factory()->for(User::factory()->employer())->create();
    }

    private function enableAi(): void
    {
        $this->app->bind(AIProvider::class, fn () => new FakeAIProvider(enabled: true));
    }

    public function test_similar_jobs_are_shown_when_ai_is_enabled(): void
    {
        $company = $this->company();

        $job = Job::factory()->active()->for($company)->create([
            'title' => 'Target Job',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
        Job::factory()->active()->for($company)->create([
            'title' => 'Closely Related Job',
            'embedding' => [0.9, 0.1],
            'embedded_at' => now(),
        ]);
        Job::factory()->active()->for($company)->create([
            'title' => 'Unrelated Job',
            'embedding' => [0.0, 1.0],
            'embedded_at' => now(),
        ]);

        $this->enableAi();

        $this->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Similar jobs')
            ->assertSee('Closely Related Job');
    }

    public function test_similar_jobs_are_hidden_when_ai_is_disabled(): void
    {
        $company = $this->company();

        $job = Job::factory()->active()->for($company)->create([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
        Job::factory()->active()->for($company)->create([
            'title' => 'Possible Match',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        // Default test config: AI_PROVIDER=none, no rebind needed.
        $this->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('Similar jobs');
    }

    public function test_similar_jobs_are_hidden_when_the_job_has_no_embedding_yet(): void
    {
        $company = $this->company();

        $job = Job::factory()->active()->for($company)->create();
        Job::factory()->active()->for($company)->create([
            'title' => 'Possible Match',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $this->enableAi();

        $this->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('Similar jobs');
    }

    public function test_similar_jobs_excludes_the_current_job_itself(): void
    {
        $company = $this->company();

        $job = Job::factory()->active()->for($company)->create([
            'title' => 'Self Job',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
        Job::factory()->active()->for($company)->create([
            'title' => 'Other Job',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $this->enableAi();

        $response = $this->get(route('jobs.show', $job))->assertOk();

        $response->assertViewHas('similarJobs', function ($similarJobs) use ($job) {
            return $similarJobs->doesntContain('id', $job->id);
        });
    }

    public function test_similar_jobs_are_limited_to_four(): void
    {
        $company = $this->company();

        $job = Job::factory()->active()->for($company)->create([
            'title' => 'Target Job',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        foreach (range(1, 6) as $i) {
            Job::factory()->active()->for($company)->create([
                'title' => "Candidate {$i}",
                'embedding' => [1.0, (float) $i],
                'embedded_at' => now(),
            ]);
        }

        $this->enableAi();

        $response = $this->get(route('jobs.show', $job))->assertOk();

        $response->assertViewHas('similarJobs', fn ($similarJobs) => $similarJobs->count() === 4);
    }
}
