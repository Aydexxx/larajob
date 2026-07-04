<?php

namespace Tests\Feature\AI;

use App\Exceptions\AIDisabledException;
use App\Jobs\ParseResume;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\AICostGuard;
use App\Services\AI\AIService;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\JobDescriptionDraftService;
use App\Services\AI\MatchExplanation;
use App\Services\AI\MatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

/**
 * Cost-control behaviour: the soft global budget guard, per-user daily
 * feature caps, CV re-parse debounce, per-feature usage counting, and the
 * caching guarantee that repeat views never re-hit the API. Every path
 * degrades to the existing rule-based / none-provider fallback.
 */
class AICostControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function useFake(FakeAIProvider $fake): void
    {
        $this->app->instance(AIProvider::class, $fake);
    }

    private function embeddedProfile(): CandidateProfile
    {
        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL',
            'experience_years' => 4,
        ]);

        $profile->forceFill(['embedding' => [1.0, 0.0], 'embedded_at' => now()])->saveQuietly();

        return $profile->refresh();
    }

    private function embeddedJob(): Job
    {
        return Job::factory()->active()->create([
            'title' => 'Backend Role',
            'description' => 'Build and maintain our platform.',
            'requirements' => 'Care about quality.',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
    }

    private function explanationJson(): string
    {
        return json_encode([
            'summary' => 'Good fit overall.',
            'strengths' => ['Solid Laravel background'],
            'gaps' => [['gap' => 'No Kubernetes', 'suggestion' => 'Try a side project.']],
        ]);
    }

    // --- Item 2: caching prevents repeat API hits ---------------------------

    public function test_repeat_explanation_views_are_served_from_cache_without_another_call(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $this->useFake($fake);
        $service = $this->app->make(MatchService::class);

        $profile = $this->embeddedProfile();
        $job = $this->embeddedJob();

        $service->explain($profile, $job);
        $service->explain($profile, $job);
        $service->explain($profile, $job);

        $this->assertSame(1, $fake->chatCalls, 'A warm (profile, job) pair must never re-hit the model.');
    }

    // --- Item 1: per-user daily caps degrade gracefully ---------------------

    public function test_match_explanation_degrades_to_rule_based_once_the_daily_cap_is_reached(): void
    {
        config(['ai.limits.match-explain.per_day' => 1]);

        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $this->useFake($fake);
        $service = $this->app->make(MatchService::class);

        $first = $service->explain($this->embeddedProfile(), $this->embeddedJob());
        $this->assertSame(MatchExplanation::SOURCE_MODEL, $first->source);
        $this->assertSame(1, $fake->chatCalls);

        // A fresh pair (cache miss) after the cap is spent must fall back to
        // the rule-based explanation with no further model call.
        $second = $service->explain($this->embeddedProfile(), $this->embeddedJob());
        $this->assertSame(MatchExplanation::SOURCE_RULES, $second->source);
        $this->assertSame(1, $fake->chatCalls, 'Over the daily cap: no more model calls.');
    }

    public function test_job_description_draft_degrades_to_template_over_the_daily_cap(): void
    {
        config(['ai.limits.job-description.per_day' => 1]);

        $fake = new FakeAIProvider(
            enabled: true,
            chatResponse: '{"description":"A model-written description of the role.","requirements":"Model requirements."}',
        );
        $this->useFake($fake);
        $service = $this->app->make(JobDescriptionDraftService::class);

        $first = $service->draft(['title' => 'Backend Engineer']);
        $this->assertSame(JobDescriptionDraftService::SOURCE_MODEL, $first['source']);

        $second = $service->draft(['title' => 'Backend Engineer']);
        $this->assertSame(JobDescriptionDraftService::SOURCE_TEMPLATE, $second['source']);
        $this->assertSame(1, $fake->chatCalls, 'Over the daily cap: template only, no model call.');
    }

    public function test_ask_about_job_returns_a_rate_limited_message_over_the_daily_cap(): void
    {
        config(['ai.limits.ask.per_day' => 1]);

        $fake = new FakeAIProvider(enabled: true, chatResponse: 'Yes, this role is remote-friendly.');
        $this->useFake($fake);

        $job = Job::factory()->active()->create();

        $this->postJson(route('jobs.ask', $job), ['question' => 'Is it remote?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Yes, this role is remote-friendly.');

        $response = $this->postJson(route('jobs.ask', $job), ['question' => 'What is the salary?'])
            ->assertOk();

        $this->assertStringContainsString("reached today's limit", $response->json('answer'));
        $this->assertSame(1, $fake->chatCalls, 'Over the daily cap: no more model calls.');
    }

    // --- Item 5: per-feature usage counting ---------------------------------

    public function test_real_calls_are_counted_per_feature_for_observability(): void
    {
        config([
            'ai.provider' => 'openai',
            'ai.enabled' => true,
            'ai.chat_model' => 'gpt-4o-mini',
        ]);

        Prism::fake([
            TextResponseFake::make()->withText('ok')->withUsage(new Usage(1, 1)),
            TextResponseFake::make()->withText('ok')->withUsage(new Usage(1, 1)),
        ]);

        $ai = $this->app->make(AIService::class);
        $ai->chat('one', null, 'ask');
        $ai->chat('two', null, 'bias-check');

        $guard = $this->app->make(AICostGuard::class);
        $this->assertSame(1, $guard->callsToday('ask'));
        $this->assertSame(1, $guard->callsToday('bias-check'));
        $this->assertSame(2, $guard->totalToday());
    }

    // --- Item 4: soft global budget guard (fail safe) -----------------------

    public function test_budget_exhaustion_disables_the_ai_service_and_blocks_further_calls(): void
    {
        config([
            'ai.provider' => 'openai',
            'ai.enabled' => true,
            'ai.chat_model' => 'gpt-4o-mini',
            'ai.budget.daily_call_limit' => 1,
        ]);

        // Only one response is ever needed — the second call must be blocked
        // before it reaches Prism.
        Prism::fake([
            TextResponseFake::make()->withText('first')->withUsage(new Usage(1, 1)),
        ]);

        $ai = $this->app->make(AIService::class);

        $this->assertTrue($ai->isEnabled());
        $this->assertSame('first', $ai->chat('a', null, 'ask'));

        // Budget now spent → the layer reports disabled, so features degrade.
        $this->assertFalse($ai->isEnabled());

        $this->expectException(AIDisabledException::class);
        $ai->chat('b', null, 'ask');
    }

    // --- Item 3: CV re-parse debounce + daily cap ---------------------------

    private function candidate(): User
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->create();

        return $user->fresh();
    }

    private function pdf(string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'resume.pdf',
            file_get_contents(base_path('tests/Support/fixtures/resume.pdf')).$extra,
        );
    }

    public function test_reuploading_the_same_resume_is_debounced_and_not_reparsed(): void
    {
        config(['ai.limits.cv-parse.debounce_minutes' => 10]);
        Storage::fake(config('filesystems.resume_disk'));

        $user = $this->candidate();

        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf()]);
        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf()]);

        // Identical file within the window → parsed exactly once.
        Queue::assertPushed(ParseResume::class, 1);
    }

    public function test_cv_parse_daily_cap_skips_analysis_but_still_saves_the_file(): void
    {
        config([
            'ai.limits.cv-parse.per_day' => 1,
            'ai.limits.cv-parse.debounce_minutes' => 0,
        ]);
        Storage::fake(config('filesystems.resume_disk'));

        $user = $this->candidate();

        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf()]);
        // A genuinely different file (distinct hash) — only the cap can stop it.
        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf("\n% padding")])
            ->assertRedirect(route('candidate.profile.edit'));

        Queue::assertPushed(ParseResume::class, 1);

        // The second upload still stored its file — only the analysis was skipped.
        $this->assertNotNull($user->candidateProfile()->value('resume_path'));
    }
}
