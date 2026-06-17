<?php

namespace Tests\Feature\AI;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\MatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class EmployerMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function enableAi(array $vector = [1.0, 0.0]): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(
            enabled: true,
            vector: $vector,
            chatResponse: '{"summary":"Good fit overall.","strengths":["PHP"],"gaps":["AWS"]}',
        ));
    }

    private function employer(): User
    {
        return User::factory()->employer()->create();
    }

    private function applicationFor(User $employer, array $jobOverrides = []): Application
    {
        $company = Company::factory()->for($employer)->create();
        $job = Job::factory()->active()->for($company)->create(array_merge([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ], $jobOverrides));

        $candidate = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($candidate)->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);

        return Application::factory()->for($job)->for($candidate)->create();
    }

    /** Pre-warm the cache the way the queued job would after an apply. */
    private function warm(Application $application): void
    {
        $application->loadMissing(['job', 'user.candidateProfile']);
        $this->app->make(MatchService::class)->score(
            $application->user->candidateProfile,
            $application->job
        );
    }

    public function test_index_shows_sort_toggle_only_when_ai_enabled(): void
    {
        $employer = $this->employer();
        $this->applicationFor($employer);

        $this->enableAi();
        $this->actingAs($employer)
            ->get(route('employer.applications.index'))
            ->assertOk()
            ->assertSee('Best match');
    }

    public function test_index_hides_match_ui_when_ai_disabled(): void
    {
        $employer = $this->employer();
        $this->applicationFor($employer);

        $this->actingAs($employer)
            ->get(route('employer.applications.index'))
            ->assertOk()
            ->assertDontSee('Best match');
    }

    public function test_index_shows_cached_match_percentage(): void
    {
        $employer = $this->employer();
        $application = $this->applicationFor($employer);

        $this->enableAi();
        $this->warm($application);

        $this->actingAs($employer)
            ->get(route('employer.applications.index'))
            ->assertOk()
            ->assertSee('100% match');
    }

    public function test_index_can_sort_by_best_match(): void
    {
        $employer = $this->employer();

        // Strong match: job vector aligns with the [1,0] profile embedding.
        $strong = $this->applicationFor($employer, ['embedding' => [1.0, 0.0]]);
        // Weak match: orthogonal vector → 0%.
        $weak = $this->applicationFor($employer, ['embedding' => [0.0, 1.0]]);

        $this->enableAi();
        $this->warm($strong);
        $this->warm($weak);

        $this->actingAs($employer)
            ->get(route('employer.applications.index', ['sort' => 'match']))
            ->assertOk()
            ->assertSeeInOrder([
                $strong->user->name,
                $weak->user->name,
            ]);
    }

    public function test_match_endpoint_returns_breakdown(): void
    {
        $employer = $this->employer();
        $application = $this->applicationFor($employer);

        $this->enableAi();

        $this->actingAs($employer)
            ->getJson(route('employer.applications.match', $application))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('match.percentage', 100)
            ->assertJsonPath('match.summary', 'Good fit overall.');
    }

    public function test_match_endpoint_forbidden_for_unrelated_employer(): void
    {
        $owner = $this->employer();
        $application = $this->applicationFor($owner);

        $other = $this->employer();
        $this->enableAi();

        $this->actingAs($other)
            ->getJson(route('employer.applications.match', $application))
            ->assertForbidden();
    }

    public function test_match_endpoint_not_found_when_ai_disabled(): void
    {
        $employer = $this->employer();
        $application = $this->applicationFor($employer);

        $this->actingAs($employer)
            ->getJson(route('employer.applications.match', $application))
            ->assertNotFound();
    }

    public function test_detail_page_shows_match_card_when_ai_enabled(): void
    {
        $employer = $this->employer();
        $application = $this->applicationFor($employer);

        $this->enableAi();

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertSee('AI match');
    }
}
