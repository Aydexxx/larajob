<?php

namespace Tests\Feature\AI;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Repositories\JobMatchRepository;
use App\Services\AI\Contracts\EmbeddingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves the semantic-search pipeline runs end-to-end with AI_PROVIDER=none:
 * the EmbeddingService produces deterministic stub vectors without any API
 * key, they round-trip through the embedding columns added by the pgvector
 * migrations, and JobMatchRepository ranks jobs by cosine similarity with a
 * raw similarity score on every result.
 *
 * The suite runs on sqlite, so these tests exercise the portable fallback
 * path (JSON columns + in-memory VectorSearch). The pgsql path runs the
 * exact same contract through pgvector's <=> operator; only the execution
 * engine differs.
 */
class SemanticSearchPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep observer-dispatched embedding jobs off the sync queue so
        // fixtures stay inert (they'd no-op anyway with AI disabled).
        Queue::fake();

        $this->assertFalse(
            config('ai.enabled'),
            'These tests must run with the AI layer disabled (AI_PROVIDER=none).'
        );
    }

    private function job(array $attributes = []): Job
    {
        $company = Company::factory()->for(User::factory()->employer())->create();

        return Job::factory()->active()->for($company)->create($attributes);
    }

    /** Store an embedding the way the pipeline does: quietly, with a timestamp. */
    private function embedJob(Job $job, array $vector): Job
    {
        $job->forceFill(['embedding' => $vector, 'embedded_at' => now()])->saveQuietly();

        return $job->refresh();
    }

    public function test_embedding_columns_exist_on_both_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('job_listings', ['embedding', 'embedded_at']));
        $this->assertTrue(Schema::hasColumns('candidate_profiles', ['embedding', 'embedded_at']));
    }

    public function test_stub_embedding_has_configured_dimensions_and_unit_length(): void
    {
        $embeddings = app(EmbeddingProvider::class);

        $vector = $embeddings->embed('Senior Laravel developer in Berlin');

        $this->assertSame(config('ai.embedding_dimensions'), $embeddings->dimensions());
        $this->assertCount($embeddings->dimensions(), $vector);
        $this->assertContainsOnlyFloat($vector);

        $norm = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $vector)));
        $this->assertEqualsWithDelta(1.0, $norm, 1e-9);
    }

    public function test_stub_embedding_is_deterministic_and_discriminates_between_texts(): void
    {
        $embeddings = app(EmbeddingProvider::class);

        $this->assertSame(
            $embeddings->embed('PHP backend engineer'),
            $embeddings->embed('PHP backend engineer'),
            'The same text must always produce the same stub vector.'
        );

        $this->assertNotSame(
            $embeddings->embed('PHP backend engineer'),
            $embeddings->embed('Pastry chef'),
            'Different texts must produce different stub vectors.'
        );
    }

    public function test_candidate_profile_embedding_round_trips_through_the_database(): void
    {
        $embeddings = app(EmbeddingProvider::class);

        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create();
        $vector = $embeddings->embed('Senior PHP Engineer. Skills: PHP, Laravel, PostgreSQL');

        $profile->forceFill(['embedding' => $vector, 'embedded_at' => now()])->saveQuietly();
        $profile->refresh();

        $this->assertSame($vector, $profile->embedding);
        $this->assertNotNull($profile->embedded_at);
    }

    public function test_top_matches_ranks_stub_embedded_jobs_by_cosine_similarity(): void
    {
        $embeddings = app(EmbeddingProvider::class);

        $profileText = 'Senior Laravel engineer, PHP, PostgreSQL, remote';
        $profileVector = $embeddings->embed($profileText);

        // A job embedded from the *same* text is an exact semantic match;
        // the others get unrelated (near-orthogonal) stub vectors.
        $exact = $this->embedJob($this->job(['title' => 'Exact Match Role']), $embeddings->embed($profileText));
        $this->embedJob($this->job(['title' => 'Unrelated Role A']), $embeddings->embed('Forklift operator, warehouse'));
        $this->embedJob($this->job(['title' => 'Unrelated Role B']), $embeddings->embed('Kindergarten teacher'));

        $matches = app(JobMatchRepository::class)->topMatches($profileVector, 10);

        $this->assertCount(3, $matches);
        $this->assertTrue($matches->first()->is($exact));
        $this->assertEqualsWithDelta(1.0, $matches->first()->similarity, 1e-9);

        // Every result carries a raw cosine similarity, ordered descending.
        $similarities = $matches->pluck('similarity')->all();
        $this->assertContainsOnlyFloat($similarities);
        $sorted = $similarities;
        rsort($sorted);
        $this->assertSame($sorted, $similarities);
    }

    public function test_top_matches_returns_exact_similarity_scores_and_respects_limit(): void
    {
        $identical = $this->embedJob($this->job(['title' => 'Identical']), [1.0, 0.0, 0.0]);
        $halfway = $this->embedJob($this->job(['title' => 'Halfway']), [1.0, 1.0, 0.0]);
        $this->embedJob($this->job(['title' => 'Orthogonal']), [0.0, 1.0, 0.0]);

        $matches = app(JobMatchRepository::class)->topMatches([1.0, 0.0, 0.0], 2);

        $this->assertCount(2, $matches);
        $this->assertTrue($matches[0]->is($identical));
        $this->assertTrue($matches[1]->is($halfway));
        $this->assertEqualsWithDelta(1.0, $matches[0]->similarity, 1e-9);
        $this->assertEqualsWithDelta(1 / sqrt(2), $matches[1]->similarity, 1e-9);
    }

    public function test_top_matches_only_considers_active_jobs_with_embeddings(): void
    {
        $embedded = $this->embedJob($this->job(['title' => 'Embedded Active']), [1.0, 0.0]);
        $this->job(['title' => 'Never Embedded']);

        $draft = $this->job(['title' => 'Embedded Draft']);
        $draft->forceFill(['status' => 'draft'])->saveQuietly();
        $this->embedJob($draft, [1.0, 0.0]);

        $matches = app(JobMatchRepository::class)->topMatches([1.0, 0.0], 10);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches->first()->is($embedded));
    }

    public function test_top_matches_is_empty_for_an_empty_profile_embedding(): void
    {
        $this->embedJob($this->job(), [1.0, 0.0]);

        $this->assertTrue(app(JobMatchRepository::class)->topMatches([], 10)->isEmpty());
    }
}
