<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Contracts\EmbeddingProvider;

/**
 * The one place feature code gets embedding vectors from.
 *
 * With a real provider enabled (openai | ollama) the call is delegated to
 * {@see AIProvider::embed()}, which routes through Prism to the provider's
 * embeddings endpoint. With AI_PROVIDER=none it degrades to a deterministic
 * stub vector derived purely from the input text — no network, no API key —
 * so migrations, storage, pgvector queries and ranking can all be exercised
 * end-to-end in any environment.
 *
 * Stub properties that make it a faithful stand-in for a real embedding:
 *  - deterministic: the same text always produces the same vector,
 *  - discriminative: different texts produce (near-orthogonal) different
 *    vectors, so identical text ranks at similarity ~1.0 above everything
 *    else,
 *  - unit length: cosine math behaves exactly as with real embeddings and
 *    pgvector never sees a zero-norm vector (whose cosine distance is
 *    undefined).
 */
class EmbeddingService implements EmbeddingProvider
{
    public function __construct(
        private readonly AIProvider $ai,
    ) {}

    public function dimensions(): int
    {
        return (int) config('ai.embedding_dimensions', 1536);
    }

    public function embed(string $text): array
    {
        if (! $this->ai->isEnabled()) {
            return $this->stubVector($text);
        }

        return $this->ai->embed($text, 'embedding');
    }

    /**
     * Deterministic pseudo-embedding built from a SHA-256 stream of the
     * text. Hash-based rather than seeded rand so the output is identical
     * across PHP versions and platforms.
     *
     * @return array<int, float>
     */
    private function stubVector(string $text): array
    {
        $dimensions = $this->dimensions();
        $seed = hash('sha256', $text, true);

        // Expand the seed into 4 bytes of hash material per dimension.
        $material = '';
        for ($block = 0; strlen($material) < $dimensions * 4; $block++) {
            $material .= hash('sha256', $seed.pack('N', $block), true);
        }

        $values = [];
        $squaredNorm = 0.0;

        for ($i = 0; $i < $dimensions; $i++) {
            $word = unpack('N', substr($material, $i * 4, 4))[1];
            $value = ($word / 0xFFFFFFFF) * 2.0 - 1.0; // uniform in [-1, 1]
            $values[] = $value;
            $squaredNorm += $value * $value;
        }

        // Normalize to unit length, like real embedding models do.
        $norm = sqrt($squaredNorm);

        return array_map(fn (float $value): float => $value / $norm, $values);
    }
}
