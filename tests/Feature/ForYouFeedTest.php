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
 * The signed-in candidate landing at "/" gets a personalized "For You" feed
 * when AI is on, resolving to one of four states. With AI off the feed is an
 * AI surface that must not appear — everyone sees the marketing home.
 */
class ForYouFeedTest extends TestCase
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
            chatResponse: '{"summary":"Great fit.","strengths":["PHP"],"gaps":[]}',
        ));
    }

    private function candidate(array $profile = [], bool $embedded = true): User
    {
        $user = User::factory()->candidate()->create();
        $p = CandidateProfile::factory()->for($user)->create(array_merge([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL',
        ], $profile));

        if ($embedded) {
            $p->forceFill(['embedding' => [1.0, 0.0], 'embedded_at' => now()])->saveQuietly();
        }

        return $user->fresh();
    }

    private function activeJob(string $title): Job
    {
        return Job::factory()->active()->for(Company::factory())->create([
            'title' => $title,
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
    }

    public function test_ranked_feed_shows_matched_roles_for_an_embedded_profile(): void
    {
        $this->enableAi();
        $user = $this->candidate();
        $this->activeJob('Backend Engineer');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('For you', false)
            ->assertSee('Roles matched to you')
            ->assertSee('Backend Engineer');
    }

    public function test_unscorable_profile_sees_complete_profile_state_with_latest_fallback(): void
    {
        $this->enableAi();
        $user = $this->candidate(['skills' => null], embedded: false);
        $this->activeJob('Fallback Role');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Complete your profile to personalize this feed')
            ->assertSee('Latest opportunities')
            ->assertSee('Fallback Role');
    }

    public function test_scorable_profile_without_embedding_sees_analyzing_state(): void
    {
        $this->enableAi();
        // Scorable, but no embedding stored yet → pipeline pending.
        $user = $this->candidate(embedded: false);
        $this->activeJob('Pending Role');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee("We're analyzing your profile");
    }

    public function test_ranked_feed_with_no_matchable_jobs_shows_empty_state(): void
    {
        $this->enableAi();
        $user = $this->candidate();
        // No active embedded jobs exist at all.

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('No matching roles just yet');
    }

    public function test_feed_is_hidden_and_marketing_home_shown_when_ai_disabled(): void
    {
        // No AI binding → disabled. Even an embedded, scorable candidate
        // sees the marketing home, never the AI feed.
        $user = $this->candidate();
        $this->activeJob('Hidden Match Role');

        $response = $this->actingAs($user)->get(route('home'))->assertOk();
        $response->assertDontSee('Roles matched to you');
        $response->assertDontSee('For you', false);
    }

    public function test_employer_never_sees_the_for_you_feed(): void
    {
        $this->enableAi();
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Roles matched to you');
    }
}
