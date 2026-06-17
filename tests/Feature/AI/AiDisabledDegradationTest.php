<?php

namespace Tests\Feature\AI;

use App\Jobs\GenerateJobEmbedding;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Proves the whole application is fully functional with the AI layer off.
 *
 * The test environment ships with AI_PROVIDER=none (see phpunit.xml has no
 * AI_* overrides and config/ai.php defaults to "none"), so every test here
 * runs against the real, disabled AIService — no provider is faked. The
 * point is the opposite of the other AI tests: prove that with zero AI
 * configuration every page still renders, search still works, and not a
 * single AI affordance leaks into the UI.
 *
 * Markers asserted absent, and where they come from when AI is on:
 *   "AI match"                    -> resources/views/components/match-card
 *   "Similar jobs"                -> jobs/show (semantic similar card)
 *   "Smart search"               -> jobs/index (semantic ranking badge)
 *   "Draft with AI"               -> candidate apply form
 *   "Generate description with AI" -> employer job create form
 *   "Best match"                  -> employer applications sort control
 */
class AiDisabledDegradationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Embedding jobs are dispatched by the JobObserver on create; keep
        // them off the sync queue so fixtures don't churn (they'd no-op
        // anyway with AI disabled, but faking keeps the test hermetic).
        Queue::fake();

        $this->assertFalse(
            config('ai.enabled'),
            'These tests must run with the AI layer disabled (AI_PROVIDER=none).'
        );
    }

    private function candidate(): User
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL',
        ]);

        return $user->fresh();
    }

    /** An active job that even has an embedding stored, to prove AI UI stays
     *  hidden because the layer is off — not merely because data is missing. */
    private function embeddedJob(array $overrides = []): Job
    {
        $company = Company::factory()->for(User::factory()->employer())->create();

        return Job::factory()->active()->for($company)->create(array_merge([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ], $overrides));
    }

    public function test_homepage_renders_without_ai(): void
    {
        $this->embeddedJob();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Smart search')
            ->assertDontSee('AI match');
    }

    public function test_job_index_renders_and_hides_semantic_badge(): void
    {
        $this->embeddedJob(['title' => 'Listed Role']);

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Listed Role')
            ->assertDontSee('Smart search');
    }

    public function test_search_falls_back_to_keyword_matching_with_correct_results(): void
    {
        $company = Company::factory()->for(User::factory()->employer())->create();

        // Both have embeddings; with AI off, ranking is pure keyword SQL, so
        // only the title-matching row comes back and no semantic badge shows.
        Job::factory()->active()->for($company)->create([
            'title' => 'Unicorn Wrangler',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
        Job::factory()->active()->for($company)->create([
            'title' => 'Database Administrator',
            'embedding' => [0.0, 1.0],
            'embedded_at' => now(),
        ]);

        $this->get(route('jobs.index', ['search' => 'Unicorn']))
            ->assertOk()
            ->assertSee('Unicorn Wrangler')
            ->assertDontSee('Database Administrator')
            ->assertDontSee('Smart search');
    }

    public function test_job_detail_renders_without_match_card_or_similar_jobs(): void
    {
        $job = $this->embeddedJob(['title' => 'Target Role']);
        // A sibling that would be a "similar job" if semantic search were on.
        $this->embeddedJob(['title' => 'Sibling Role']);

        $this->actingAs($this->candidate())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Target Role')
            ->assertDontSee('AI match')
            ->assertDontSee('Similar jobs')
            ->assertDontSee('Sibling Role');
    }

    public function test_candidate_apply_page_renders_without_draft_button(): void
    {
        $job = $this->embeddedJob();

        $this->actingAs($this->candidate())
            ->get(route('candidate.applications.create', ['job_id' => $job->id]))
            ->assertOk()
            ->assertSee('Cover Letter')
            ->assertDontSee('Draft with AI');
    }

    public function test_candidate_dashboard_and_applications_pages_render(): void
    {
        $candidate = $this->candidate();

        $this->actingAs($candidate)->get(route('candidate.dashboard'))->assertOk();
        $this->actingAs($candidate)->get(route('candidate.applications.index'))->assertOk();
    }

    public function test_employer_job_create_page_renders_without_generator(): void
    {
        $employer = User::factory()->employer()->create();
        Company::factory()->for($employer)->create();

        $this->actingAs($employer)
            ->get(route('employer.jobs.create'))
            ->assertOk()
            ->assertSee('Description')
            ->assertDontSee('Generate description with AI');
    }

    public function test_employer_applications_pages_render_without_match_ui(): void
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();
        $job = Job::factory()->active()->for($company)->create();
        $application = Application::factory()->for($job)->for($this->candidate())->create();

        $this->actingAs($employer)
            ->get(route('employer.applications.index'))
            ->assertOk()
            ->assertDontSee('Best match');

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertDontSee('AI match');
    }

    public function test_all_ai_json_endpoints_are_not_found_when_disabled(): void
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();
        $job = Job::factory()->active()->for($company)->create();
        $application = Application::factory()->for($job)->for($this->candidate())->create();
        $candidate = $job->applications()->first()->user;

        // Candidate-facing match + cover-letter draft endpoints.
        $this->actingAs($candidate)
            ->getJson(route('candidate.jobs.match', $job))
            ->assertNotFound();
        $this->actingAs($candidate)
            ->postJson(route('candidate.applications.draft-cover-letter'), ['job_id' => $job->id])
            ->assertNotFound();

        // Employer-facing match + description draft endpoints.
        $this->actingAs($employer)
            ->getJson(route('employer.applications.match', $application))
            ->assertNotFound();
        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), [
                'title' => 'Backend Engineer',
                'bullets' => ['Owns the API'],
            ])
            ->assertNotFound();
    }

    public function test_embedding_queue_job_is_a_clean_no_op_when_disabled(): void
    {
        $job = $this->embeddedJob(['embedding' => null, 'embedded_at' => null]);

        // Resolve the real, container-wired job + provider (AI disabled) and
        // run it inline: it must neither throw nor write an embedding.
        $this->app->call([new GenerateJobEmbedding($job->id), 'handle']);

        $job->refresh();
        $this->assertNull($job->embedding);
        $this->assertNull($job->embedded_at);
    }

    public function test_embed_command_exits_gracefully_when_disabled(): void
    {
        $this->embeddedJob(['embedding' => null, 'embedded_at' => null]);

        $this->artisan('jobs:embed')
            ->expectsOutputToContain('AI layer is disabled')
            ->assertSuccessful();
    }
}
