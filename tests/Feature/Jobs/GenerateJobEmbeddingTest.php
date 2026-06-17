<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateJobEmbedding;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class GenerateJobEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_embedding_when_ai_is_enabled(): void
    {
        Queue::fake();
        $job = $this->makeJob();

        $ai = new FakeAIProvider(enabled: true, vector: [0.1, 0.2, 0.3]);

        (new GenerateJobEmbedding($job->id))->handle($ai);

        $job->refresh();
        $this->assertSame(1, $ai->embedCalls);
        $this->assertSame([0.1, 0.2, 0.3], $job->embedding);
        $this->assertNotNull($job->embedded_at);
    }

    public function test_it_does_nothing_when_ai_is_disabled(): void
    {
        Queue::fake();
        $job = $this->makeJob();

        $ai = new FakeAIProvider(enabled: false);

        (new GenerateJobEmbedding($job->id))->handle($ai);

        $job->refresh();
        $this->assertSame(0, $ai->embedCalls);
        $this->assertNull($job->embedding);
        $this->assertNull($job->embedded_at);
    }

    public function test_it_does_not_throw_when_the_job_no_longer_exists(): void
    {
        $ai = new FakeAIProvider(enabled: true);

        (new GenerateJobEmbedding(999_999))->handle($ai);

        $this->assertSame(0, $ai->embedCalls);
    }

    public function test_storing_the_embedding_does_not_retrigger_the_observer(): void
    {
        Queue::fake();
        $job = $this->makeJob();

        Queue::fake(); // reset push history captured during creation

        (new GenerateJobEmbedding($job->id))->handle(new FakeAIProvider(enabled: true));

        Queue::assertNotPushed(GenerateJobEmbedding::class);
    }

    private function makeJob(): Job
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();

        return Job::factory()->for($company)->create();
    }
}
