<?php

namespace Tests\Feature;

use App\Jobs\GenerateProfileEmbedding;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CandidateProfileObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_profile_queues_an_embedding_job(): void
    {
        Queue::fake();

        $profile = $this->makeProfile();

        Queue::assertPushed(
            GenerateProfileEmbedding::class,
            fn (GenerateProfileEmbedding $queued) => $queued->profileId === $profile->id
        );
    }

    public function test_updating_an_embeddable_field_queues_an_embedding_job(): void
    {
        $profile = $this->makeProfile(['headline' => 'Original Headline']);

        Queue::fake();

        $profile->update(['headline' => 'Updated Headline']);

        Queue::assertPushed(
            GenerateProfileEmbedding::class,
            fn (GenerateProfileEmbedding $queued) => $queued->profileId === $profile->id
        );
    }

    public function test_updating_a_non_embeddable_field_does_not_queue_an_embedding_job(): void
    {
        $profile = $this->makeProfile(['phone' => '555-0100']);

        Queue::fake();

        $profile->update(['phone' => '555-0199']);

        Queue::assertNotPushed(GenerateProfileEmbedding::class);
    }

    private function makeProfile(array $overrides = []): CandidateProfile
    {
        $candidate = User::factory()->candidate()->create();

        return CandidateProfile::factory()->for($candidate)->create($overrides);
    }
}
