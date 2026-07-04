<?php

namespace Tests\Feature\Console;

use App\Jobs\GenerateJobEmbedding;
use App\Jobs\GenerateProfileEmbedding;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The command dispatches unconditionally (never gates on AI being enabled)
 * since queuing is cheap and the queued jobs already no-op safely when AI
 * is disabled — see GenerateJobEmbedding/GenerateProfileEmbedding. These
 * tests run with AI_PROVIDER=none and assert dispatch counts only.
 */
class BackfillEmbeddingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_only_unembedded_jobs_and_profiles_by_default(): void
    {
        $this->makeJob(['embedded_at' => null]);
        $this->makeJob(['embedded_at' => null]);
        $this->makeJob(['embedded_at' => now()]);

        $this->makeProfile(['embedded_at' => null]);
        $this->makeProfile(['embedded_at' => now()]);

        Queue::fake();

        $this->artisan('larajob:backfill-embeddings')->assertSuccessful();

        Queue::assertPushed(GenerateJobEmbedding::class, 2);
        Queue::assertPushed(GenerateProfileEmbedding::class, 1);
    }

    public function test_force_option_queues_every_job_and_profile(): void
    {
        $this->makeJob(['embedded_at' => null]);
        $this->makeJob(['embedded_at' => now()]);
        $this->makeJob(['embedded_at' => now()]);

        $this->makeProfile(['embedded_at' => now()]);
        $this->makeProfile(['embedded_at' => now()]);

        Queue::fake();

        $this->artisan('larajob:backfill-embeddings', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(GenerateJobEmbedding::class, 3);
        Queue::assertPushed(GenerateProfileEmbedding::class, 2);
    }

    public function test_it_queues_nothing_when_everything_already_has_an_embedding(): void
    {
        $this->makeJob(['embedded_at' => now()]);
        $this->makeProfile(['embedded_at' => now()]);

        Queue::fake();

        $this->artisan('larajob:backfill-embeddings')->assertSuccessful();

        Queue::assertNotPushed(GenerateJobEmbedding::class);
        Queue::assertNotPushed(GenerateProfileEmbedding::class);
    }

    private function makeJob(array $overrides = []): Job
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();

        return Job::factory()->for($company)->create($overrides);
    }

    private function makeProfile(array $overrides = []): CandidateProfile
    {
        $candidate = User::factory()->candidate()->create();

        return CandidateProfile::factory()->for($candidate)->create($overrides);
    }
}
