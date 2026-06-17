<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable result of scoring a candidate profile against a job.
 *
 * The percentage is derived from embedding similarity (deterministic); the
 * summary/strengths/gaps come from a single LLM call and are always present
 * (an empty strengths/gaps list is used when the model output can't be
 * parsed). Stored in the cache as a plain array (see {@see MatchService})
 * so it survives any cache driver, and reconstructed via {@see fromArray}.
 *
 * @implements Arrayable<string, mixed>
 */
final class MatchResult implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int, string>  $strengths
     * @param  array<int, string>  $gaps
     */
    public function __construct(
        public readonly int $percentage,
        public readonly string $summary,
        public readonly array $strengths,
        public readonly array $gaps,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            percentage: (int) ($data['percentage'] ?? 0),
            summary: (string) ($data['summary'] ?? ''),
            strengths: array_values(array_filter((array) ($data['strengths'] ?? []), 'is_string')),
            gaps: array_values(array_filter((array) ($data['gaps'] ?? []), 'is_string')),
        );
    }

    /**
     * Coarse tier used to pick UI colors. Kept here so candidate and
     * employer views agree on the thresholds.
     */
    public function tier(): string
    {
        return match (true) {
            $this->percentage >= 75 => 'high',
            $this->percentage >= 50 => 'medium',
            default => 'low',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'percentage' => $this->percentage,
            'summary' => $this->summary,
            'strengths' => $this->strengths,
            'gaps' => $this->gaps,
            'tier' => $this->tier(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
