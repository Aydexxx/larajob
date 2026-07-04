<?php

namespace Tests\Unit\Services\AI;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\MatchExplanation;
use App\Services\AI\MatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

/**
 * Covers MatchService::explain(): the deterministic score with rule-based
 * hard-requirement adjustments, the provider-agnostic narrative (model vs
 * rules), and — critically — the caching contract: a warm (profile
 * embedding version, job version) pair must never trigger another AI call.
 */
class MatchExplainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function serviceWith(FakeAIProvider $fake): MatchService
    {
        $this->app->instance(AIProvider::class, $fake);

        return $this->app->make(MatchService::class);
    }

    /** A profile with a stored embedding, as the async pipeline leaves it. */
    private function embeddedProfile(array $overrides = []): CandidateProfile
    {
        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create(array_merge([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL, AWS',
            'experience_years' => 3,
        ], $overrides));

        $profile->forceFill(['embedding' => [1.0, 0.0], 'embedded_at' => now()])->saveQuietly();

        return $profile->refresh();
    }

    /** A job with fixed text (no accidental years/skills) and a stored embedding. */
    private function embeddedJob(array $overrides = []): Job
    {
        return Job::factory()->active()->create(array_merge([
            'title' => 'Backend Role',
            'description' => 'Build and maintain our platform.',
            'requirements' => 'Care about quality.',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ], $overrides));
    }

    private function explanationJson(): string
    {
        return json_encode([
            'summary' => 'Good fit overall.',
            'strengths' => ['Solid Laravel background'],
            'gaps' => [['gap' => 'No Kubernetes experience', 'suggestion' => 'Deploy a side project to a small cluster.']],
        ]);
    }

    public function test_second_call_hits_the_cache_and_makes_no_ai_calls(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $service = $this->serviceWith($fake);
        $profile = $this->embeddedProfile();
        $job = $this->embeddedJob();

        $first = $service->explain($profile, $job);

        // Stored vectors on both sides → not even the first call embeds.
        $this->assertSame(0, $fake->embedCalls);
        $this->assertSame(1, $fake->chatCalls);

        $second = $service->explain($profile, $job);

        $this->assertSame(1, $fake->chatCalls, 'A warm pair must not trigger another LLM call.');
        $this->assertSame(0, $fake->embedCalls);
        $this->assertEquals($first->toArray(), $second->toArray());
    }

    public function test_unrelated_profile_edits_do_not_invalidate_the_cache(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $service = $this->serviceWith($fake);
        $profile = $this->embeddedProfile();
        $job = $this->embeddedJob();

        $service->explain($profile, $job);

        // Editing a field that does not feed matching bumps updated_at but
        // NOT the embedding version — the cached explanation must survive.
        $this->travel(2)->minutes();
        $profile->update(['phone' => '+90 555 000 0000']);

        $service->explain($profile->refresh(), $job);

        $this->assertSame(1, $fake->chatCalls, 'Non-matching profile edits must not burn an API call.');
    }

    public function test_profile_embedding_change_invalidates_the_cache(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $service = $this->serviceWith($fake);
        $profile = $this->embeddedProfile();
        $job = $this->embeddedJob();

        $service->explain($profile, $job);

        // A re-generated embedding (new embedded_at) is the profile-side
        // version signal.
        $this->travel(2)->minutes();
        $profile->forceFill(['embedding' => [0.5, 0.5], 'embedded_at' => now()])->saveQuietly();

        $service->explain($profile->refresh(), $job);

        $this->assertSame(2, $fake->chatCalls);
    }

    public function test_job_change_invalidates_the_cache(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $service = $this->serviceWith($fake);
        $profile = $this->embeddedProfile();
        $job = $this->embeddedJob();

        $service->explain($profile, $job);

        $this->travel(2)->minutes();
        $job->update(['title' => 'Renamed Backend Role']);

        $service->explain($profile, $job->refresh());

        $this->assertSame(2, $fake->chatCalls);
    }

    public function test_rule_based_explanation_with_ai_disabled_makes_no_api_calls(): void
    {
        $fake = new FakeAIProvider(enabled: false);
        $service = $this->serviceWith($fake);

        // No stored embeddings: the base must come from structured overlap.
        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL, AWS',
            'experience_years' => 3,
        ]);
        $job = Job::factory()->active()->create([
            'title' => 'Backend Role',
            'description' => 'Build and maintain our platform.',
            'requirements' => 'Strong PHP and Laravel required.',
        ]);

        $result = $service->explain($profile, $job);

        // 2 of 4 profile skills appear in the posting → base 50, no stated
        // years requirement → no adjustment.
        $this->assertSame(50, $result->score);
        $this->assertSame(MatchExplanation::SOURCE_RULES, $result->source);
        $this->assertStringContainsString('PHP, Laravel', $result->strengths[0]);
        $this->assertNotSame('', $result->summary);
        $this->assertNotEmpty($result->gaps);
        $this->assertStringContainsString('2 of the 4 skills', $result->gaps[0]['gap']);
        $this->assertNotSame('', $result->gaps[0]['suggestion'], 'Every rule-based gap carries an actionable suggestion.');

        $this->assertSame(0, $fake->embedCalls + $fake->chatCalls, 'The rule-based path must never call the provider.');
    }

    public function test_failing_a_hard_years_requirement_reduces_the_score_and_adds_a_gap(): void
    {
        $fake = new FakeAIProvider(enabled: false);
        $service = $this->serviceWith($fake);

        $jobAttributes = [
            'title' => 'Backend Role',
            'description' => 'Build and maintain our platform.',
            'requirements' => 'Strong PHP and Laravel required. At least 5 years of experience.',
        ];

        $makeProfile = fn (int $years) => CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL, AWS',
            'experience_years' => $years,
        ]);

        // Base is 50 (2 of 4 skills) in both cases; only the years differ.
        $failing = $service->explain($makeProfile(2), Job::factory()->active()->create($jobAttributes));
        $meeting = $service->explain($makeProfile(7), Job::factory()->active()->create($jobAttributes));

        $this->assertSame(35, $failing->score, 'Missing the hard requirement costs the penalty (50 - 15).');
        $this->assertSame(55, $meeting->score, 'Meeting it earns the bonus (50 + 5).');

        $yearsGap = collect($failing->gaps)->first(fn (array $gap) => str_contains($gap['gap'], '5+ years'));
        $this->assertNotNull($yearsGap, 'The failed hard requirement must be surfaced as a gap.');
        $this->assertStringContainsString('your profile lists 2', $yearsGap['gap']);
        $this->assertNotSame('', $yearsGap['suggestion']);

        $this->assertTrue(
            collect($meeting->strengths)->contains(fn (string $s) => str_contains($s, 'minimum of 5 years')),
            'Meeting the requirement should be listed as a strength.'
        );
    }

    public function test_model_narrative_with_structured_gaps_is_used_when_ai_enabled(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $service = $this->serviceWith($fake);

        $result = $service->explain($this->embeddedProfile(), $this->embeddedJob());

        $this->assertSame(MatchExplanation::SOURCE_MODEL, $result->source);
        $this->assertSame(100, $result->score, 'Score is deterministic (aligned vectors), never model output.');
        $this->assertSame('Good fit overall.', $result->summary);
        $this->assertSame(['Solid Laravel background'], $result->strengths);
        $this->assertSame('No Kubernetes experience', $result->gaps[0]['gap']);
        $this->assertSame('Deploy a side project to a small cluster.', $result->gaps[0]['suggestion']);
    }

    public function test_malformed_model_output_degrades_to_the_rule_based_narrative_without_retry(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: 'I will not produce JSON today.');
        $service = $this->serviceWith($fake);

        $result = $service->explain($this->embeddedProfile(), $this->embeddedJob());

        $this->assertSame(MatchExplanation::SOURCE_RULES, $result->source);
        $this->assertSame(100, $result->score, 'The deterministic score survives a narrative failure.');
        $this->assertSame(1, $fake->chatCalls, 'A failed narrative is not retried (cost discipline).');
        $this->assertNotSame('', $result->summary);
    }

    public function test_breakdown_partitions_profile_skills_into_matched_and_unmatched(): void
    {
        $fake = new FakeAIProvider(enabled: false);
        $service = $this->serviceWith($fake);

        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL, AWS',
            'experience_years' => 3,
        ]);
        $job = Job::factory()->active()->create([
            'title' => 'Backend Role',
            'description' => 'Build and maintain our platform.',
            'requirements' => 'Strong PHP and Laravel required.',
        ]);

        $result = $service->explain($profile, $job);

        // Only the skills that literally appear in the posting are "matched";
        // the rest stay on the profile as unmatched — never invented.
        $this->assertSame(['PHP', 'Laravel'], $result->matchedSkills);
        $this->assertSame(['MySQL', 'AWS'], $result->unmatchedSkills);

        // The breakdown is exposed in the payload the views consume.
        $breakdown = $result->toArray()['breakdown'];
        $this->assertSame(['PHP', 'Laravel'], $breakdown['matchedSkills']);
        $this->assertSame(['MySQL', 'AWS'], $breakdown['unmatchedSkills']);
        $this->assertSame('none', $breakdown['experience']['status'], 'No stated requirement → nothing to compare.');
        $this->assertNull($breakdown['experience']['label']);

        $this->assertSame(0, $fake->embedCalls + $fake->chatCalls, 'The breakdown is deterministic — no provider calls.');
    }

    public function test_breakdown_states_the_experience_delta_in_plain_language(): void
    {
        $fake = new FakeAIProvider(enabled: false);
        $service = $this->serviceWith($fake);

        $jobAttributes = [
            'title' => 'Backend Role',
            'description' => 'Build and maintain our platform.',
            'requirements' => 'Strong PHP and Laravel required. At least 5 years of experience.',
        ];
        $makeProfile = fn (?int $years) => CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
            'experience_years' => $years,
        ]);

        $short = $service->explain($makeProfile(2), Job::factory()->active()->create($jobAttributes));
        $this->assertSame('unmet', $short->experienceStatus());
        $this->assertSame('Wants 5+ years — you have 2.', $short->experienceLabel());

        $enough = $service->explain($makeProfile(7), Job::factory()->active()->create($jobAttributes));
        $this->assertSame('met', $enough->experienceStatus());
        $this->assertSame('Wants 5+ years — your profile lists 7.', $enough->experienceLabel());

        $unknown = $service->explain($makeProfile(null), Job::factory()->active()->create($jobAttributes));
        $this->assertSame('unknown', $unknown->experienceStatus());
        $this->assertSame('Wants 5+ years — add your experience to compare.', $unknown->experienceLabel());
    }

    public function test_breakdown_is_carried_by_the_model_narrative_path_too(): void
    {
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $this->explanationJson());
        $service = $this->serviceWith($fake);

        $job = $this->embeddedJob([
            'requirements' => 'Strong PHP and Laravel required.',
        ]);
        $result = $service->explain($this->embeddedProfile(), $job);

        // The narrative is the model's, but the factual breakdown is still the
        // deterministic overlap — the two can never disagree.
        $this->assertSame(MatchExplanation::SOURCE_MODEL, $result->source);
        $this->assertSame(['PHP', 'Laravel'], $result->matchedSkills);
        $this->assertSame(['MySQL', 'AWS'], $result->unmatchedSkills);

        $this->assertSkillCountAgreesWithNarrative($result);
    }

    /**
     * The bug the demo exposed: the model narrative can legitimately cite a
     * candidate skill as a strength on semantic grounds even when that skill's
     * exact term never appears in the posting text — so a purely literal
     * overlap counted it as *unmatched*, and the chips read "0 matched" while
     * the prose praised the very same skill. A skill named in the narrative
     * must count as matched, so the count and the explanation can never
     * contradict each other.
     */
    public function test_a_skill_named_in_the_narrative_is_counted_as_matched(): void
    {
        $chat = json_encode([
            'summary' => 'Strong overall fit for this backend role.',
            // Praises AWS even though the posting text below never mentions it.
            'strengths' => ['Extensive AWS and cloud infrastructure experience'],
            'gaps' => [],
        ]);
        $fake = new FakeAIProvider(enabled: true, vector: [1.0, 0.0], chatResponse: $chat);
        $service = $this->serviceWith($fake);

        // Only PHP literally appears in the posting; AWS does not.
        $job = $this->embeddedJob(['requirements' => 'Strong PHP required.']);
        $result = $service->explain($this->embeddedProfile(), $job);

        $this->assertSame(MatchExplanation::SOURCE_MODEL, $result->source);

        // PHP is present literally; AWS is promoted because the narrative
        // praises it — neither is left contradicting the prose.
        $this->assertContains('PHP', $result->matchedSkills);
        $this->assertContains('AWS', $result->matchedSkills, 'A skill cited by the narrative must count as matched.');
        $this->assertNotContains('AWS', $result->unmatchedSkills);

        // Skills the narrative never mentions and the posting never states stay
        // honestly unmatched — reconciliation never invents overlap.
        $this->assertContains('MySQL', $result->unmatchedSkills);

        $this->assertSkillCountAgreesWithNarrative($result);
    }

    /**
     * Invariant guard: no candidate skill may be narrated as a strength while
     * being counted as unmatched. Applied across the other explanation tests
     * so the count and the written explanation can never drift apart.
     */
    private function assertSkillCountAgreesWithNarrative(MatchExplanation $result): void
    {
        $strengthText = strtolower(implode("\n", $result->strengths));

        foreach ($result->unmatchedSkills as $skill) {
            $this->assertStringNotContainsString(
                strtolower($skill),
                $strengthText,
                "Skill \"{$skill}\" is narrated as a strength but counted as unmatched — the count and narrative contradict.",
            );
        }

        $this->assertSame(
            [],
            array_values(array_intersect($result->matchedSkills, $result->unmatchedSkills)),
            'A skill cannot be both matched and unmatched.',
        );
    }
}
