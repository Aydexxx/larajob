<?php

namespace Tests\Feature;

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
 * Match rings on the browse-jobs cards: shown for an embedded candidate when
 * AI is on (with an accessible score label), and never for guests, employers,
 * or when AI is off.
 */
class MatchRingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function enableAi(): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: '{"summary":"x","strengths":[],"gaps":[]}',
        ));
    }

    private function embeddedCandidate(): User
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ])->forceFill(['embedding' => [1.0, 0.0], 'embedded_at' => now()])->saveQuietly();

        return $user->fresh();
    }

    private function activeJob(): Job
    {
        return Job::factory()->active()->for(Company::factory())->create([
            'title' => 'Ring Role',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
    }

    public function test_candidate_sees_an_accessible_match_ring_on_job_cards(): void
    {
        $this->enableAi();
        $this->activeJob();

        $this->actingAs($this->embeddedCandidate())
            ->get(route('jobs.index'))
            ->assertOk()
            // Aligned vectors → 100% match; the ring exposes it accessibly.
            ->assertSee('Match score 100 out of 100');
    }

    public function test_guest_sees_no_match_ring(): void
    {
        $this->enableAi();
        $this->activeJob();

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertDontSee('Match score');
    }

    public function test_no_ring_when_ai_disabled(): void
    {
        $this->activeJob();

        $this->actingAs($this->embeddedCandidate())
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertDontSee('Match score');
    }
}
