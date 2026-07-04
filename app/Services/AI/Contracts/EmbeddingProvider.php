<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

/**
 * Provider-agnostic contract for producing embedding vectors.
 *
 * Unlike {@see AIProvider::embed()}, implementations of this contract MUST
 * always return a vector — never throw because the AI layer is off. With
 * AI_PROVIDER=none the implementation returns a deterministic stub vector,
 * so the whole semantic-search pipeline (store → index → rank) runs
 * end-to-end without an API key. Callers that need to hide AI features from
 * the UI should keep gating on AIProvider::isEnabled(); this contract only
 * guarantees the plumbing works.
 */
interface EmbeddingProvider
{
    /**
     * The dimensionality every returned vector has. Matches the vector(N)
     * database columns (see ai.embedding_dimensions).
     */
    public function dimensions(): int;

    /**
     * Embed the given text with the active provider, or return a
     * deterministic stub vector when no provider is enabled. The same text
     * always yields the same vector within a provider/model configuration.
     *
     * @return array<int, float> A vector of {@see dimensions()} floats.
     */
    public function embed(string $text): array;
}
