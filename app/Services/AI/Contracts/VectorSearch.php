<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use Illuminate\Support\Collection;

/**
 * Provider-agnostic contract for ranking records by embedding similarity.
 *
 * The current implementation ({@see \App\Services\AI\VectorSearch}) ranks
 * an in-memory collection by computing cosine similarity in PHP — fine at
 * the dozens-to-low-thousands of rows this app deals with. If that ever
 * stops being fast enough, swap in a different implementation (e.g. one
 * that issues a `pgvector` `ORDER BY embedding <=> ?` query, or calls out
 * to Pinecone/Weaviate) and rebind it in the service container. Because
 * every caller depends on this interface — never on VectorSearch or
 * Eloquent directly — that swap is a one-line container change, with no
 * changes to calling code. Same pattern as AIProvider/AIService.
 */
interface VectorSearch
{
    /**
     * Cosine similarity between two equal-length vectors, in [-1, 1].
     * Returns 0.0 for empty or zero-magnitude vectors.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float;

    /**
     * Rank candidates by similarity to a query vector, most similar first.
     *
     * Each candidate must expose an `embedding` property/attribute holding
     * an `array<int, float>` (or null/empty, in which case it is skipped).
     * Matching candidates are returned with a `similarity` value attached.
     *
     * @param  array<int, float>  $queryVector
     * @param  Collection<int, mixed>  $candidates
     * @return Collection<int, mixed>
     */
    public function search(array $queryVector, Collection $candidates, int $limit): Collection;
}
