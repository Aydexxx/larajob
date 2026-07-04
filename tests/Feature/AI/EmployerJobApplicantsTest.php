<?php

namespace Tests\Feature\AI;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class EmployerJobApplicantsTest extends TestCase
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
            chatResponse: '{"summary":"Strong backend overlap.","strengths":["PHP","Laravel"],"gaps":["AWS"]}',
        ));
    }

    private function employer(): User
    {
        return User::factory()->employer()->create();
    }

    private function jobFor(User $employer, array $overrides = []): Job
    {
        $company = Company::factory()->for($employer)->create();

        return Job::factory()->active()->for($company)->create(array_merge([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ], $overrides));
    }

    private function applicant(Job $job, array $profile = []): Application
    {
        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create(array_merge([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
            'embedding' => [1.0, 0.0],
        ], $profile));

        return Application::factory()->for($job)->for($candidate->fresh())->create();
    }

    public function test_applicants_page_ranks_higher_scoring_candidate_first(): void
    {
        $this->enableAi();
        $employer = $this->employer();
        $job = $this->jobFor($employer);

        // Strong: profile embedding aligns with the job's [1,0] vector.
        $strong = $this->applicant($job, ['embedding' => [1.0, 0.0]]);
        // Weak: orthogonal embedding → 0%.
        $weak = $this->applicant($job, ['embedding' => [0.0, 1.0]]);

        $this->actingAs($employer)
            ->get(route('employer.jobs.applicants', $job))
            ->assertOk()
            ->assertSeeInOrder([
                $strong->user->name,
                $weak->user->name,
            ]);
    }

    public function test_applicants_page_ranks_even_when_ai_disabled(): void
    {
        // No AI binding → disabled. Ranking still works via the deterministic
        // score (skill-overlap fallback), and the page renders scores.
        $employer = $this->employer();
        $job = $this->jobFor($employer);
        $this->applicant($job);

        $this->actingAs($employer)
            ->get(route('employer.jobs.applicants', $job))
            ->assertOk()
            ->assertSee('match');
    }

    public function test_applicants_page_forbidden_for_non_owner(): void
    {
        $this->enableAi();
        $owner = $this->employer();
        $job = $this->jobFor($owner);
        $this->applicant($job);

        $other = $this->employer();

        $this->actingAs($other)
            ->get(route('employer.jobs.applicants', $job))
            ->assertForbidden();
    }

    public function test_summary_endpoint_returns_model_summary_when_ai_enabled(): void
    {
        $this->enableAi();
        $employer = $this->employer();
        $job = $this->jobFor($employer);
        $application = $this->applicant($job);

        $this->actingAs($employer)
            ->getJson(route('employer.jobs.applicant-summary', [$job, $application]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('summary.sentence', 'Strong backend overlap.')
            ->assertJsonPath('summary.reason', 'PHP')
            ->assertJsonPath('summary.source', 'model');
    }

    public function test_summary_endpoint_degrades_to_rules_when_ai_disabled(): void
    {
        $employer = $this->employer();
        $job = $this->jobFor($employer);
        $application = $this->applicant($job);

        $this->actingAs($employer)
            ->getJson(route('employer.jobs.applicant-summary', [$job, $application]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('summary.source', 'rules');
    }

    public function test_summary_endpoint_forbidden_for_non_owner(): void
    {
        $this->enableAi();
        $owner = $this->employer();
        $job = $this->jobFor($owner);
        $application = $this->applicant($job);

        $other = $this->employer();

        $this->actingAs($other)
            ->getJson(route('employer.jobs.applicant-summary', [$job, $application]))
            ->assertForbidden();
    }

    public function test_summary_endpoint_404s_when_application_belongs_to_another_job(): void
    {
        $this->enableAi();
        $employer = $this->employer();
        $jobA = $this->jobFor($employer);
        $jobB = $this->jobFor($employer);
        $application = $this->applicant($jobA);

        // Same owner, but the application isn't for jobB → 404, not 403.
        $this->actingAs($employer)
            ->getJson(route('employer.jobs.applicant-summary', [$jobB, $application]))
            ->assertNotFound();
    }

    public function test_summary_endpoint_reports_incomplete_profile(): void
    {
        $this->enableAi();
        $employer = $this->employer();
        $job = $this->jobFor($employer);
        $application = $this->applicant($job, ['skills' => null]);

        $this->actingAs($employer)
            ->getJson(route('employer.jobs.applicant-summary', [$job, $application]))
            ->assertOk()
            ->assertJsonPath('status', 'incomplete_profile')
            ->assertJsonPath('summary', null);
    }
}
