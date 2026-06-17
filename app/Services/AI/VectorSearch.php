<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\VectorSearch as VectorSearchContract;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * In-memory cosine-similarity ranking. See {@see VectorSearchContract} for
 * why callers should depend on the interface rather than this class.
 */
class VectorSearch implements VectorSearchContract
{
    public function cosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Vectors must be of equal length to compute cosine similarity.');
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dotProduct += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    public function search(array $queryVector, Collection $candidates, int $limit): Collection
    {
        return $candidates
            ->filter(fn (mixed $candidate): bool => $this->vectorOf($candidate) !== [])
            ->map(function (mixed $candidate) use ($queryVector) {
                $candidate->similarity = $this->cosineSimilarity($queryVector, $this->vectorOf($candidate));

                return $candidate;
            })
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();
    }

    /**
     * @return array<int, float>
     */
    private function vectorOf(mixed $candidate): array
    {
        $vector = $candidate->embedding ?? null;

        return is_array($vector) ? $vector : [];
    }
}
