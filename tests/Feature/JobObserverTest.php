<?php

namespace Tests\Feature;

use App\Jobs\GenerateJobEmbedding;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_job_queues_an_embedding_job(): void
    {
        Queue::fake();

        $job = $this->makeJob();

        Queue::assertPushed(
            GenerateJobEmbedding::class,
            fn (GenerateJobEmbedding $queued) => $queued->jobId === $job->id
        );
    }

    public function test_updating_an_embeddable_field_queues_an_embedding_job(): void
    {
        $job = $this->makeJob(['title' => 'Original Title']);

        Queue::fake();

        $job->update(['title' => 'Updated Title']);

        Queue::assertPushed(
            GenerateJobEmbedding::class,
            fn (GenerateJobEmbedding $queued) => $queued->jobId === $job->id
        );
    }

    public function test_updating_a_non_embeddable_field_does_not_queue_an_embedding_job(): void
    {
        $job = $this->makeJob(['status' => 'draft']);

        Queue::fake();

        $job->update(['status' => 'closed']);

        Queue::assertNotPushed(GenerateJobEmbedding::class);
    }

    private function makeJob(array $overrides = []): Job
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();

        return Job::factory()->for($company)->create($overrides);
    }
}
