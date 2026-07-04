<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable explained match between a candidate profile and a job.
 *
 * The score is always deterministic: embedding cosine similarity (or the
 * structured skill-overlap ratio when no vectors are available) plus
 * rule-based adjustments for hard requirements — never model output. Only
 * the narrative varies by provider: 'model' when an LLM wrote it, 'rules'
 * when it was derived purely from the structured overlap (AI disabled or
 * model output unusable).
 *
 * Gaps are structured: each carries what is missing AND a short actionable
 * suggestion to close it.
 *
 * @implements Arrayable<string, mixed>
 */
final class MatchExplanation implements Arrayable, JsonSerializable
{
    public const SOURCE_MODEL = 'model';

    public const SOURCE_RULES = 'rules';

    /**
     * @param  array<int, string>  $strengths
     * @param  array<int, array{gap: string, suggestion: string}>  $gaps
     * @param  array<int, string>  $matchedSkills  candidate skills found in the posting
     * @param  array<int, string>  $unmatchedSkills  candidate skills NOT found in the posting
     */
    public function __construct(
        public readonly int $score,
        public readonly string $summary,
        public readonly array $strengths,
        public readonly array $gaps,
        public readonly string $source,
        public readonly array $matchedSkills = [],
        public readonly array $unmatchedSkills = [],
        public readonly ?int $requiredYears = null,
        public readonly ?int $experienceYears = null,
    ) {}

    /**
     * Rebuild from a cached array. Defensive against shape drift: wrong
     * types degrade to empty values rather than throw.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // The breakdown is stored nested (see toArray) — that's the shape the
        // cache holds and rebuilds from. Fall back to flat keys for tolerance.
        $breakdown = is_array($data['breakdown'] ?? null) ? $data['breakdown'] : [];
        $experience = is_array($breakdown['experience'] ?? null) ? $breakdown['experience'] : [];

        return new self(
            score: max(0, min(100, (int) ($data['score'] ?? 0))),
            summary: is_string($data['summary'] ?? null) ? $data['summary'] : '',
            strengths: array_values(array_filter((array) ($data['strengths'] ?? []), 'is_string')),
            gaps: self::gapList($data['gaps'] ?? []),
            source: ($data['source'] ?? null) === self::SOURCE_MODEL ? self::SOURCE_MODEL : self::SOURCE_RULES,
            matchedSkills: self::skillList($breakdown['matchedSkills'] ?? $data['matchedSkills'] ?? []),
            unmatchedSkills: self::skillList($breakdown['unmatchedSkills'] ?? $data['unmatchedSkills'] ?? []),
            requiredYears: self::intOrNull($experience['requiredYears'] ?? $data['requiredYears'] ?? null),
            experienceYears: self::intOrNull($experience['experienceYears'] ?? $data['experienceYears'] ?? null),
        );
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Same thresholds as {@see MatchResult::tier()} so both match UIs agree.
     */
    public function tier(): string
    {
        return match (true) {
            $this->score >= 75 => 'high',
            $this->score >= 50 => 'medium',
            default => 'low',
        };
    }

    /**
     * How the candidate's stated experience compares to the posting's parsed
     * minimum. Drives the plain-language experience row in the breakdown:
     *
     *  - 'none'    the posting states no minimum → nothing to compare
     *  - 'unknown' a minimum is stated but the profile omits years
     *  - 'met'     the candidate meets or exceeds the minimum
     *  - 'unmet'   the candidate falls short
     */
    public function experienceStatus(): string
    {
        if ($this->requiredYears === null) {
            return 'none';
        }

        if ($this->experienceYears === null) {
            return 'unknown';
        }

        return $this->experienceYears >= $this->requiredYears ? 'met' : 'unmet';
    }

    /**
     * A single plain-language sentence describing the experience comparison,
     * or null when the posting states no minimum. Computed server-side so the
     * instant (Blade) and async (Alpine/JSON) render paths show identical copy.
     */
    public function experienceLabel(): ?string
    {
        return match ($this->experienceStatus()) {
            'unknown' => "Wants {$this->requiredYears}+ years — add your experience to compare.",
            'met' => "Wants {$this->requiredYears}+ years — your profile lists {$this->experienceYears}.",
            'unmet' => "Wants {$this->requiredYears}+ years — you have {$this->experienceYears}.",
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            // Alias consumed by the shared match-card component, which also
            // renders MatchResult payloads (employer flow).
            'percentage' => $this->score,
            'tier' => $this->tier(),
            'summary' => $this->summary,
            'strengths' => $this->strengths,
            'gaps' => $this->gaps,
            'source' => $this->source,
            // Deterministic, factual breakdown behind the score — rendered as
            // matched/unmatched skill chips and a plain-language experience
            // row. Nested so the async JSON and the cached instant-render
            // path share one shape.
            'breakdown' => [
                'matchedSkills' => $this->matchedSkills,
                'unmatchedSkills' => $this->unmatchedSkills,
                'experience' => [
                    // Raw values kept so fromArray() can faithfully rebuild the
                    // comparison from the cache; status/label are the rendered
                    // forms the views consume.
                    'requiredYears' => $this->requiredYears,
                    'experienceYears' => $this->experienceYears,
                    'status' => $this->experienceStatus(),
                    'label' => $this->experienceLabel(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Normalize a decoded value into a clean list of non-empty skill strings.
     *
     * @return array<int, string>
     */
    private static function skillList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{gap: string, suggestion: string}>
     */
    private static function gapList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function (mixed $item): ?array {
                if (is_string($item) && trim($item) !== '') {
                    return ['gap' => trim($item), 'suggestion' => ''];
                }

                if (is_array($item) && is_string($item['gap'] ?? null) && trim($item['gap']) !== '') {
                    return [
                        'gap' => trim($item['gap']),
                        'suggestion' => is_string($item['suggestion'] ?? null) ? trim($item['suggestion']) : '',
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
