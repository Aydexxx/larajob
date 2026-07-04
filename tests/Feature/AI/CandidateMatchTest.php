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

    public function test_match_endpoint_returns_explained_match_when_ai_enabled_and_profile_complete(): void
    {
        $this->enableAi();
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->getJson(route('candidate.jobs.match', $job))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('match.score', 100)
            ->assertJsonPath('match.percentage', 100)
            ->assertJsonPath('match.source', 'model')
            ->assertJsonPath('match.strengths', ['PHP', 'Laravel'])
            // A bare-string gap from the model is tolerated and structured.
            ->assertJsonPath('match.gaps.0.gap', 'AWS');
    }

    public function test_match_endpoint_includes_the_deterministic_breakdown(): void
    {
        // The breakdown is the same regardless of provider; use the free
        // rule-based path so the assertion doesn't depend on the model.
        $user = $this->candidateWithProfile(['skills' => 'PHP, Laravel, MySQL', 'experience_years' => 2]);
        $job = Job::factory()->active()->for(Company::factory())->create([
            'requirements' => 'Strong PHP and Laravel skills. At least 5 years of experience.',
        ]);

        $this->actingAs($user)
            ->getJson(route('candidate.jobs.match', $job))
            ->assertOk()
            ->assertJsonPath('match.breakdown.matchedSkills', ['PHP', 'Laravel'])
            ->assertJsonPath('match.breakdown.unmatchedSkills', ['MySQL'])
            ->assertJsonPath('match.breakdown.experience.status', 'unmet')
            ->assertJsonPath('match.breakdown.experience.label', 'Wants 5+ years — you have 2.');
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

    public function test_match_endpoint_serves_a_rule_based_explanation_when_ai_disabled(): void
    {
        // No AI binding → default provider is disabled. Since the explained
        // match phase, the endpoint no longer 404s: it degrades to the
        // rule-based explanation built from structured overlap, no API call.
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->getJson(route('candidate.jobs.match', $job))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('match.source', 'rules');
    }

    public function test_job_page_shows_match_card_for_candidate_when_ai_enabled(): void
    {
        $this->enableAi();
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Your match');
    }

    public function test_job_page_hides_match_card_when_ai_disabled(): void
    {
        $user = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('Your match');
    }

    public function test_job_page_hides_match_card_for_non_candidates(): void
    {
        $this->enableAi();
        $employer = User::factory()->employer()->create();
        $job = $this->activeJob();

        $this->actingAs($employer)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('Your match');
    }
}
