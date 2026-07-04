<?php

namespace Tests\Feature\Candidate;

use App\Jobs\ParseResume;
use App\Models\CandidateProfile;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use App\Services\Resume\PdfTextExtractor;
use App\Services\Resume\ResumeStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

/**
 * End-to-end coverage of the CV-parsing pipeline: upload → queued parse →
 * stored suggestion → review screen → explicit apply/dismiss. Runs against
 * the real PDF fixture and, where a model response is needed, a fake
 * provider — never a real API.
 */
class ResumeParsingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Resumes live on the env-selected private disk (config: filesystems
        // .resume_disk), which is "local" in the test environment.
        Storage::fake(config('filesystems.resume_disk'));
    }

    /** Store a resume the way the app does, returning its disk-relative path. */
    private function storeResume(): string
    {
        return app(ResumeStorage::class)->store($this->fixturePdf());
    }

    private function candidateWithProfile(array $overrides = []): User
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->create($overrides);

        return $user->fresh();
    }

    private function fixturePdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'resume.pdf',
            file_get_contents(base_path('tests/Support/fixtures/resume.pdf'))
        );
    }

    private function parsedJson(): string
    {
        return json_encode([
            'headline' => 'Senior Backend Engineer',
            'bio' => 'Experienced PHP and Laravel engineer with 8 years building web platforms.',
            'skills' => ['PHP', 'Laravel', 'PostgreSQL'],
            'years_of_experience' => 8,
            'location' => 'Istanbul, Turkey',
            'links' => ['https://www.linkedin.com/in/janedev'],
        ]);
    }

    /** Store a suggestion directly, as ParseResume would. */
    private function withSuggestion(User $user, ?array $suggestion = null): CandidateProfile
    {
        $profile = $user->candidateProfile;
        $profile->forceFill([
            'suggested_profile' => $suggestion ?? json_decode($this->parsedJson(), true),
            'suggested_at' => now(),
        ])->saveQuietly();

        return $profile->refresh();
    }

    public function test_uploading_a_resume_queues_the_parse_job(): void
    {
        $user = $this->candidateWithProfile();

        Queue::fake();

        $this->actingAs($user)
            ->put(route('candidate.profile.update'), ['resume' => $this->fixturePdf()])
            ->assertRedirect(route('candidate.profile.edit'));

        Queue::assertPushed(
            ParseResume::class,
            fn (ParseResume $queued) => $queued->profileId === $user->candidateProfile->id
        );
    }

    public function test_updating_the_profile_without_a_resume_does_not_queue_a_parse(): void
    {
        $user = $this->candidateWithProfile();

        Queue::fake();

        $this->actingAs($user)
            ->put(route('candidate.profile.update'), ['headline' => 'New Headline'])
            ->assertRedirect(route('candidate.profile.edit'));

        Queue::assertNotPushed(ParseResume::class);
    }

    public function test_parse_job_stores_a_suggestion_and_never_touches_live_fields(): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(chatResponse: $this->parsedJson()));

        $user = $this->candidateWithProfile([
            'headline' => 'Original Headline',
            'bio' => 'Original bio.',
        ]);
        $profile = $user->candidateProfile;
        $profile->forceFill([
            'resume_path' => $this->storeResume(),
        ])->saveQuietly();

        $this->app->call([new ParseResume($profile->id), 'handle']);

        $profile->refresh();
        $this->assertSame('Senior Backend Engineer', $profile->suggested_profile['headline']);
        $this->assertSame(['PHP', 'Laravel', 'PostgreSQL'], $profile->suggested_profile['skills']);
        $this->assertNotNull($profile->suggested_at);

        // The parse alone must not modify the profile itself.
        $this->assertSame('Original Headline', $profile->headline);
        $this->assertSame('Original bio.', $profile->bio);
    }

    public function test_parse_job_with_ai_disabled_stores_the_empty_suggestion_so_the_flow_still_runs(): void
    {
        $this->assertFalse(config('ai.enabled'));

        $user = $this->candidateWithProfile(['headline' => 'Original Headline']);
        $profile = $user->candidateProfile;
        $profile->forceFill([
            'resume_path' => $this->storeResume(),
        ])->saveQuietly();

        $this->app->call([new ParseResume($profile->id), 'handle']);

        $profile->refresh();
        $this->assertTrue($profile->hasPendingSuggestion());
        $this->assertNull($profile->suggested_profile['headline']);
        $this->assertSame([], $profile->suggested_profile['skills']);
        $this->assertSame('Original Headline', $profile->headline);
    }

    public function test_parse_job_stores_the_empty_suggestion_when_the_model_returns_malformed_json(): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(chatResponse: 'not { json'));

        $user = $this->candidateWithProfile(['headline' => 'Original Headline']);
        $profile = $user->candidateProfile;
        $profile->forceFill([
            'resume_path' => $this->storeResume(),
        ])->saveQuietly();

        $this->app->call([new ParseResume($profile->id), 'handle']);

        $profile->refresh();
        $this->assertTrue($profile->hasPendingSuggestion());
        $this->assertNull($profile->suggested_profile['headline']);
        $this->assertSame('Original Headline', $profile->headline, 'A malformed model response must leave the profile untouched.');
    }

    public function test_profile_edit_page_shows_the_review_banner_when_a_suggestion_is_pending(): void
    {
        $user = $this->candidateWithProfile();
        $this->withSuggestion($user);

        $this->actingAs($user)
            ->get(route('candidate.profile.edit'))
            ->assertOk()
            ->assertSee('Review suggestions');
    }

    public function test_profile_edit_page_loads_for_a_candidate_without_a_profile(): void
    {
        // Regression: with no profile row yet, the controller renders
        // `new CandidateProfile`, whose resume_analyzing attribute is null.
        // The view calls isAnalyzingResume(); before it coerced to bool that
        // returned null and the typed accessor threw a 500.
        $user = User::factory()->candidate()->create();

        $this->assertNull($user->candidateProfile);

        $this->actingAs($user)
            ->get(route('candidate.profile.edit'))
            ->assertOk();
    }

    public function test_is_analyzing_resume_reads_false_when_the_flag_is_null(): void
    {
        // A profile whose flag was never set — an unpersisted instance, or a
        // legacy row written before the column had a default — must read as
        // "not analyzing" rather than returning null from the bool accessor.
        $profile = new CandidateProfile;

        $this->assertNull($profile->resume_analyzing, 'The raw attribute is null (the bool cast leaves null untouched).');
        $this->assertFalse($profile->isAnalyzingResume());
    }

    public function test_review_screen_shows_suggested_values_alongside_current_ones(): void
    {
        $user = $this->candidateWithProfile(['headline' => 'Original Headline']);
        $this->withSuggestion($user);

        $this->actingAs($user)
            ->get(route('candidate.profile.resume-suggestions.show'))
            ->assertOk()
            ->assertSee('Senior Backend Engineer')
            ->assertSee('PHP, Laravel, PostgreSQL')
            ->assertSee('Istanbul, Turkey')
            ->assertSee('https://www.linkedin.com/in/janedev')
            ->assertSee('Original Headline')
            ->assertSee('applying will replace it');
    }

    public function test_review_screen_shows_a_friendly_message_for_an_empty_suggestion(): void
    {
        $user = $this->candidateWithProfile();
        $this->withSuggestion($user, [
            'headline' => null, 'bio' => null, 'skills' => [],
            'years_of_experience' => null, 'location' => null, 'links' => [],
        ]);

        $this->actingAs($user)
            ->get(route('candidate.profile.resume-suggestions.show'))
            ->assertOk()
            ->assertSee('extract any profile information')
            ->assertSee('Your profile has not been changed');
    }

    public function test_review_screen_redirects_when_no_suggestion_is_pending(): void
    {
        $user = $this->candidateWithProfile();

        $this->actingAs($user)
            ->get(route('candidate.profile.resume-suggestions.show'))
            ->assertRedirect(route('candidate.profile.edit'));
    }

    public function test_applying_selected_fields_updates_only_those_and_clears_the_suggestion(): void
    {
        $user = $this->candidateWithProfile([
            'headline' => 'Original Headline',
            'location' => 'Original Location',
        ]);
        $profile = $this->withSuggestion($user);

        $this->actingAs($user)
            ->post(route('candidate.profile.resume-suggestions.store'), [
                'apply' => ['headline', 'skills'],
                'values' => [
                    'headline' => 'Senior Backend Engineer (edited)',
                    'skills' => 'PHP, Laravel',
                    'location' => 'Istanbul, Turkey',
                ],
            ])
            ->assertRedirect(route('candidate.profile.edit'));

        $profile->refresh();
        $this->assertSame('Senior Backend Engineer (edited)', $profile->headline, 'Applied fields use the user-edited value.');
        $this->assertSame('PHP, Laravel', $profile->skills);
        $this->assertSame('Original Location', $profile->location, 'Unticked fields must never be applied.');
        $this->assertFalse($profile->hasPendingSuggestion());
    }

    public function test_applying_nothing_leaves_the_profile_untouched_and_clears_the_suggestion(): void
    {
        $user = $this->candidateWithProfile(['headline' => 'Original Headline']);
        $profile = $this->withSuggestion($user);
        $updatedAt = $profile->updated_at;

        $this->actingAs($user)
            ->post(route('candidate.profile.resume-suggestions.store'), ['apply' => []])
            ->assertRedirect(route('candidate.profile.edit'));

        $profile->refresh();
        $this->assertSame('Original Headline', $profile->headline);
        $this->assertTrue($profile->updated_at->equalTo($updatedAt));
        $this->assertFalse($profile->hasPendingSuggestion());
    }

    public function test_dismissing_clears_the_suggestion_without_touching_the_profile(): void
    {
        $user = $this->candidateWithProfile(['headline' => 'Original Headline']);
        $profile = $this->withSuggestion($user);

        $this->actingAs($user)
            ->delete(route('candidate.profile.resume-suggestions.destroy'))
            ->assertRedirect(route('candidate.profile.edit'));

        $profile->refresh();
        $this->assertSame('Original Headline', $profile->headline);
        $this->assertFalse($profile->hasPendingSuggestion());
    }

    public function test_pdf_text_extraction_reads_the_fixture_resume(): void
    {
        $text = app(PdfTextExtractor::class)
            ->extract(file_get_contents(base_path('tests/Support/fixtures/resume.pdf')));

        $this->assertStringContainsString('Jane Developer', $text);
        $this->assertStringContainsString('PHP, Laravel, PostgreSQL, Redis, Docker', $text);
    }
}
