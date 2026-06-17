<?php

namespace Tests\Feature\AI;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class CoverLetterDraftTest extends TestCase
{
    use RefreshDatabase;

    private function enableAi(string $chatResponse = 'Dear team, I would love to join...'): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(
            enabled: true,
            chatResponse: $chatResponse,
        ));
    }

    private function activeJob(): Job
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();

        return Job::factory()->active()->for($company)->create();
    }

    private function candidateWithProfile(array $overrides = []): User
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->create(array_merge([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ], $overrides));

        return $user->fresh();
    }

    public function test_draft_endpoint_returns_generated_text_when_ai_enabled_and_profile_usable(): void
    {
        $this->enableAi('I am excited to apply for this role...');
        $candidate = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($candidate)
            ->postJson(route('candidate.applications.draft-cover-letter'), ['job_id' => $job->id])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('draft', 'I am excited to apply for this role...');
    }

    public function test_draft_endpoint_reports_incomplete_profile(): void
    {
        $this->enableAi();
        $candidate = $this->candidateWithProfile(['headline' => null, 'bio' => null, 'skills' => null]);
        $job = $this->activeJob();

        $this->actingAs($candidate)
            ->postJson(route('candidate.applications.draft-cover-letter'), ['job_id' => $job->id])
            ->assertOk()
            ->assertJsonPath('status', 'incomplete_profile')
            ->assertJsonPath('draft', null);
    }

    public function test_draft_endpoint_is_not_found_when_ai_disabled(): void
    {
        $candidate = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($candidate)
            ->postJson(route('candidate.applications.draft-cover-letter'), ['job_id' => $job->id])
            ->assertNotFound();
    }

    public function test_draft_endpoint_rejects_a_job_id_for_an_inactive_job(): void
    {
        $this->enableAi();
        $candidate = $this->candidateWithProfile();

        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();
        $closedJob = Job::factory()->for($company)->create(['status' => 'closed', 'expires_at' => null]);

        $this->actingAs($candidate)
            ->postJson(route('candidate.applications.draft-cover-letter'), ['job_id' => $closedJob->id])
            ->assertNotFound();
    }

    public function test_draft_endpoint_is_rate_limited_per_user(): void
    {
        $this->enableAi();
        $candidate = $this->candidateWithProfile();
        $job = $this->activeJob();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($candidate)
                ->postJson(route('candidate.applications.draft-cover-letter'), ['job_id' => $job->id])
                ->assertOk();
        }

        $this->actingAs($candidate)
            ->postJson(route('candidate.applications.draft-cover-letter'), ['job_id' => $job->id])
            ->assertStatus(429);
    }

    public function test_apply_page_shows_draft_with_ai_button_when_ai_enabled(): void
    {
        $this->enableAi();
        $candidate = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($candidate)
            ->get(route('candidate.applications.create', ['job_id' => $job->id]))
            ->assertOk()
            ->assertSee('Draft with AI');
    }

    public function test_apply_page_hides_draft_with_ai_button_when_ai_disabled(): void
    {
        $candidate = $this->candidateWithProfile();
        $job = $this->activeJob();

        $this->actingAs($candidate)
            ->get(route('candidate.applications.create', ['job_id' => $job->id]))
            ->assertOk()
            ->assertDontSee('Draft with AI');
    }
}
