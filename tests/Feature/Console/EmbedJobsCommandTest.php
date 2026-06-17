<?php

namespace Tests\Feature\Console;

use App\Jobs\GenerateJobEmbedding;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class EmbedJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_warns_and_exits_cleanly_when_ai_is_disabled(): void
    {
        // Default test config has AI_PROVIDER=none, so AIService::isEnabled() is false.
        $this->artisan('jobs:embed')
            ->expectsOutputToContain('AI layer is disabled')
            ->assertSuccessful();
    }

    public function test_it_queues_only_jobs_missing_an_embedding_by_default(): void
    {
        Queue::fake();

        $this->makeJob(['embedded_at' => now(), 'embedding' => [0.1, 0.2]]);
        $missing = $this->makeJob();

        $this->app->bind(AIProvider::class, fn () => new FakeAIProvider(enabled: true));
        Queue::fake(); // reset push history captured while creating the fixtures above

        $this->artisan('jobs:embed')->assertSuccessful();

        Queue::assertPushed(GenerateJobEmbedding::class, 1);
        Queue::assertPushed(
            GenerateJobEmbedding::class,
            fn (GenerateJobEmbedding $queued) => $queued->jobId === $missing->id
        );
    }

    public function test_force_option_requeues_every_job(): void
    {
        Queue::fake();

        $this->makeJob(['embedded_at' => now(), 'embedding' => [0.1, 0.2]]);
        $this->makeJob();

        $this->app->bind(AIProvider::class, fn () => new FakeAIProvider(enabled: true));
        Queue::fake(); // reset push history captured while creating the fixtures above

        $this->artisan('jobs:embed', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(GenerateJobEmbedding::class, 2);
    }

    public function test_it_reports_when_there_is_nothing_to_embed(): void
    {
        Queue::fake();

        $this->makeJob(['embedded_at' => now(), 'embedding' => [0.1, 0.2]]);

        $this->app->bind(AIProvider::class, fn () => new FakeAIProvider(enabled: true));
        Queue::fake();

        $this->artisan('jobs:embed')
            ->expectsOutputToContain('already have embeddings')
            ->assertSuccessful();

        Queue::assertNotPushed(GenerateJobEmbedding::class);
    }

    private function makeJob(array $overrides = []): Job
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();

        return Job::factory()->for($company)->create($overrides);
    }
}
