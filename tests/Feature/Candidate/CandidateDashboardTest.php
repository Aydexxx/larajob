<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

/**
 * Covers the "Learn these to unlock more matches" widget on the candidate
 * dashboard: secondary to the For You feed, and only present when
 * SkillGapAdvisorService actually has something to say.
 */
class CandidateDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function vectorAtSimilarity(float $similarity): array
    {
        return [$similarity, sqrt(max(0.0, 1 - $similarity ** 2))];
    }

    public function test_the_widget_shows_high_leverage_skills_when_available(): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(enabled: true));

        // Vocabulary source: another candidate who already has the skill.
        CandidateProfile::factory()->for(User::factory()->candidate())->create(['skills' => 'Kubernetes']);

        $candidate = User::factory()->candidate()->create();
        $profile = CandidateProfile::factory()->for($candidate)->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);
        $profile->forceFill(['embedding' => [1.0, 0.0], 'embedded_at' => now()])->saveQuietly();

        $job = Job::factory()->active()->for(Company::factory())->create([
            'title' => 'Backend Role',
            'description' => 'Build great things.',
            'requirements' => 'Kubernetes experience required.',
        ]);
        $job->forceFill(['embedding' => $this->vectorAtSimilarity(0.6), 'embedded_at' => now()])->saveQuietly();

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertOk()
            ->assertSee('Learn these to unlock more matches')
            ->assertSee('Kubernetes');
    }

    public function test_the_widget_is_hidden_when_ai_is_disabled(): void
    {
        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertOk()
            ->assertDontSee('Learn these to unlock more matches');
    }

    public function test_the_widget_is_hidden_when_the_profile_has_no_embedding_yet(): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(enabled: true));

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertOk()
            ->assertDontSee('Learn these to unlock more matches');
    }

    public function test_the_widget_is_hidden_when_there_are_no_near_miss_jobs(): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(enabled: true));

        $candidate = User::factory()->candidate()->create();
        $profile = CandidateProfile::factory()->for($candidate)->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);
        $profile->forceFill(['embedding' => [1.0, 0.0], 'embedded_at' => now()])->saveQuietly();
        // No jobs at all exist — nothing to aggregate.

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertOk()
            ->assertDontSee('Learn these to unlock more matches');
    }
}
