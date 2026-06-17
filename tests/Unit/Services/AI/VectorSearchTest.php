<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\VectorSearch;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VectorSearchTest extends TestCase
{
    private VectorSearch $vectorSearch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vectorSearch = new VectorSearch;
    }

    public function test_identical_vectors_have_similarity_of_one(): void
    {
        $vector = [1.0, 2.0, 3.0];

        $this->assertEqualsWithDelta(1.0, $this->vectorSearch->cosineSimilarity($vector, $vector), 0.0001);
    }

    public function test_opposite_vectors_have_similarity_of_negative_one(): void
    {
        $a = [1.0, 0.0];
        $b = [-1.0, 0.0];

        $this->assertEqualsWithDelta(-1.0, $this->vectorSearch->cosineSimilarity($a, $b), 0.0001);
    }

    public function test_orthogonal_vectors_have_similarity_of_zero(): void
    {
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];

        $this->assertEqualsWithDelta(0.0, $this->vectorSearch->cosineSimilarity($a, $b), 0.0001);
    }

    public function test_empty_vectors_return_zero_instead_of_dividing_by_zero(): void
    {
        $this->assertSame(0.0, $this->vectorSearch->cosineSimilarity([], [1.0, 2.0]));
        $this->assertSame(0.0, $this->vectorSearch->cosineSimilarity([1.0, 2.0], []));
    }

    public function test_zero_magnitude_vector_returns_zero_instead_of_dividing_by_zero(): void
    {
        $zero = [0.0, 0.0, 0.0];
        $other = [1.0, 2.0, 3.0];

        $this->assertSame(0.0, $this->vectorSearch->cosineSimilarity($zero, $other));
    }

    public function test_mismatched_vector_lengths_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->vectorSearch->cosineSimilarity([1.0, 2.0], [1.0, 2.0, 3.0]);
    }

    public function test_search_ranks_candidates_by_similarity_descending(): void
    {
        $query = [1.0, 0.0];

        $exactMatch = (object) ['embedding' => [1.0, 0.0]];
        $orthogonal = (object) ['embedding' => [0.0, 1.0]];
        $opposite = (object) ['embedding' => [-1.0, 0.0]];

        $results = $this->vectorSearch->search($query, new Collection([$orthogonal, $opposite, $exactMatch]), 3);

        $this->assertSame($exactMatch, $results[0]);
        $this->assertSame($orthogonal, $results[1]);
        $this->assertSame($opposite, $results[2]);
        $this->assertEqualsWithDelta(1.0, $results[0]->similarity, 0.0001);
    }

    public function test_search_respects_the_limit(): void
    {
        $query = [1.0, 0.0];

        $candidates = new Collection([
            (object) ['embedding' => [1.0, 0.0]],
            (object) ['embedding' => [0.9, 0.1]],
            (object) ['embedding' => [0.0, 1.0]],
        ]);

        $results = $this->vectorSearch->search($query, $candidates, 2);

        $this->assertCount(2, $results);
    }

    public function test_search_skips_candidates_without_an_embedding(): void
    {
        $query = [1.0, 0.0];

        $withEmbedding = (object) ['embedding' => [1.0, 0.0]];
        $withoutEmbedding = (object) ['embedding' => null];
        $withEmptyEmbedding = (object) ['embedding' => []];

        $results = $this->vectorSearch->search(
            $query,
            new Collection([$withEmbedding, $withoutEmbedding, $withEmptyEmbedding]),
            10
        );

        $this->assertCount(1, $results);
        $this->assertSame($withEmbedding, $results[0]);
    }
}
