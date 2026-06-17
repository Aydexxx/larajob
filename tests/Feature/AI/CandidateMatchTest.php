<?php

namespace Tests\Feature\AI;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class CandidateMatchTest extends TestCase
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
            chatResponse: '{"summary":"Strong overlap in backend skills.","strengths":["PHP","Laravel"],"gaps":["AWS"]}',
        ));
    }

    private function candidateWithProfile(array $overrides = []): User
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->create(array_merge([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL',
        ], $overrides));

        return $user->fresh();
    }

    private function activeJob(): Job
    {
        return Job::factory()->active()->for(Company::factory())->create([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
    }

    public function test_match_endpoint_returns_score_when_ai_enabled_and_profile_complete(): void
    {
        $this->enableAi();
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->getJson(route('candidate.jobs.match', $job))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('match.percentage', 100)
            ->assertJsonPath('match.strengths', ['PHP', 'Laravel'])
            ->assertJsonPath('match.gaps', ['AWS']);
    }

    public function test_match_endpoint_reports_incomplete_profile(): void
    {
        $this->enableAi();
        $user = $this->candidateWithProfile(['skills' => null]);
        $job = $this->activeJob();

        $this->actingAs($user)
            ->getJson(route('candidate.jobs.match', $job))
            ->assertOk()
            ->assertJsonPath('status', 'incomplete_profile')
            ->assertJsonPath('match', null);
    }

    public function test_match_endpoint_is_not_found_when_ai_disabled(): void
    {
        // No AI binding → default provider is disabled.
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->getJson(route('candidate.jobs.match', $job))
            ->assertNotFound();
    }

    public function test_job_page_shows_match_card_for_candidate_when_ai_enabled(): void
    {
        $this->enableAi();
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('AI match');
    }

    public function test_job_page_hides_match_card_when_ai_disabled(): void
    {
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('AI match');
    }

    public function test_job_page_hides_match_card_for_non_candidates(): void
    {
        $this->enableAi();
        $employer = User::factory()->employer()->create();
        $job = $this->activeJob();

        $this->actingAs($employer)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('AI match');
    }
}
