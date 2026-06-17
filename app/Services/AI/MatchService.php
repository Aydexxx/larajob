<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Contracts\VectorSearch as VectorSearchContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scores how well a candidate profile matches a job.
 *
 * Hybrid by design: the percentage is a deterministic embedding cosine
 * similarity (cheap, repeatable), while the human-readable
 * summary/strengths/gaps come from a single LLM call constrained to strict
 * JSON. Model output is never trusted — parsing is defensive and falls back
 * to an embedding-only result if anything is off.
 *
 * Every (profile, job) result is cached. The cache key embeds both records'
 * `updated_at` timestamps and the active models, so edits (or a provider
 * switch) invalidate naturally and a given pair is never scored twice while
 * warm. Callers that must stay cheap (e.g. list views) use {@see cached()},
 * which only ever reads — it never triggers an LLM call.
 */
class MatchService
{
    /** Results stay warm for a day; the key also self-invalidates on edits. */
    private const CACHE_TTL_MINUTES = 1440;

    private const MAX_STRENGTHS = 5;

    private const MAX_GAPS = 4;

    public function __construct(
        private readonly AIProvider $ai,
        private readonly VectorSearchContract $vectorSearch,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    /**
     * A profile is scorable once it has the textual substance the scorer
     * needs: a set of skills plus at least a headline or bio.
     */
    public function profileIsScorable(?CandidateProfile $profile): bool
    {
        return $profile !== null
            && filled($profile->skills)
            && (filled($profile->headline) || filled($profile->bio));
    }

    /**
     * Score the pair, computing (and caching) on a miss. Cost guard: a warm
     * pair is returned straight from cache with no embedding or LLM call.
     */
    public function score(CandidateProfile $profile, Job $job): MatchResult
    {
        $data = Cache::remember(
            $this->cacheKey($profile, $job),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->compute($profile, $job)->toArray(),
        );

        return MatchResult::fromArray($data);
    }

    /**
     * Read a previously computed result without ever computing one. Returns
     * null when the pair isn't cached yet — used by list views that must
     * never block or spend tokens.
     */
    public function cached(CandidateProfile $profile, Job $job): ?MatchResult
    {
        $data = Cache::get($this->cacheKey($profile, $job));

        return is_array($data) ? MatchResult::fromArray($data) : null;
    }

    private function compute(CandidateProfile $profile, Job $job): MatchResult
    {
        $percentage = $this->baseScore($profile, $job);

        [$summary, $strengths, $gaps] = $this->narrative($profile, $job, $percentage);

        return new MatchResult($percentage, $summary, $strengths, $gaps);
    }

    /**
     * Deterministic 0-100 score from embedding cosine similarity. Cosine in
     * [-1, 1] is clamped to [0, 1] before scaling — negative similarity is
     * simply "no match".
     */
    private function baseScore(CandidateProfile $profile, Job $job): int
    {
        $profileVector = $this->profileEmbedding($profile);
        $jobVector = $this->jobEmbedding($job);

        if ($profileVector === [] || $jobVector === []) {
            return 0;
        }

        $cosine = $this->vectorSearch->cosineSimilarity($profileVector, $jobVector);
        $clamped = max(0.0, min(1.0, $cosine));

        return (int) round($clamped * 100);
    }

    /**
     * One LLM call for the narrative, constrained to strict JSON and parsed
     * defensively. Any failure (exception or unparseable output) degrades to
     * an embedding-only result so the score is always usable.
     *
     * @return array{0: string, 1: array<int, string>, 2: array<int, string>}
     */
    private function narrative(CandidateProfile $profile, Job $job, int $percentage): array
    {
        try {
            $raw = $this->ai->chat($this->prompt($profile, $job, $percentage), $this->systemPrompt());
            $parsed = $this->parseJson($raw);

            if ($parsed !== null) {
                return [$parsed['summary'], $parsed['strengths'], $parsed['gaps']];
            }

            Log::channel('ai')->warning('Match narrative could not be parsed; using fallback', [
                'profile_id' => $profile->id,
                'job_id' => $job->id,
            ]);
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Match narrative call failed; using fallback', [
                'profile_id' => $profile->id,
                'job_id' => $job->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return [$this->fallbackSummary($percentage), [], []];
    }

    /**
     * Extract and validate a strict-JSON object from raw model output.
     * Tolerates surrounding prose / markdown fences by slicing to the
     * outermost braces. Returns null if the result isn't usable.
     *
     * @return array{summary: string, strengths: array<int, string>, gaps: array<int, string>}|null
     */
    private function parseJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            return null;
        }

        $summary = isset($decoded['summary']) && is_string($decoded['summary'])
            ? trim($decoded['summary'])
            : '';

        if ($summary === '') {
            return null;
        }

        return [
            'summary' => Str::limit($summary, 400),
            'strengths' => $this->stringList($decoded['strengths'] ?? [], self::MAX_STRENGTHS),
            'gaps' => $this->stringList($decoded['gaps'] ?? [], self::MAX_GAPS),
        ];
    }

    /**
     * Normalize an arbitrary decoded value into a capped list of clean,
     * non-empty short strings.
     *
     * @return array<int, string>
     */
    private function stringList(mixed $value, int $limit): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => Str::limit(trim($item), 120))
            ->take($limit)
            ->values()
            ->all();
    }

    private function fallbackSummary(int $percentage): string
    {
        return match (true) {
            $percentage >= 75 => 'Strong alignment between this profile and the role based on overall skills and experience.',
            $percentage >= 50 => 'Moderate alignment between this profile and the role; some areas line up well.',
            default => 'Limited overlap between this profile and the role based on the available information.',
        };
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You assess how well a job candidate fits a role.',
            'Respond with a SINGLE valid JSON object and nothing else — no prose, no markdown, no code fences.',
            'Shape: {"summary": string, "strengths": string[], "gaps": string[]}.',
            'summary is one or two concise sentences.',
            'strengths lists concrete reasons the candidate fits (max 5 short items).',
            'gaps lists concrete missing or weaker areas (max 4 short items).',
            'Do not invent facts that are not supported by the inputs.',
        ]);
    }

    private function prompt(CandidateProfile $profile, Job $job, int $percentage): string
    {
        return implode("\n", [
            "An embedding-similarity match score of {$percentage}% has already been computed for this pair.",
            'Write the explanation consistent with that score.',
            '',
            '== CANDIDATE PROFILE ==',
            $this->profileText($profile),
            '',
            '== JOB ==',
            $this->jobText($job),
        ]);
    }

    private function profileText(CandidateProfile $profile): string
    {
        return implode("\n", array_filter([
            $profile->headline,
            $profile->bio,
            filled($profile->skills) ? "Skills: {$profile->skills}" : null,
            $profile->experience_years !== null ? "Years of experience: {$profile->experience_years}" : null,
            filled($profile->location) ? "Location: {$profile->location}" : null,
        ]));
    }

    private function jobText(Job $job): string
    {
        return implode("\n", array_filter([
            $job->title,
            $job->description,
            $job->requirements,
            filled($job->location) ? "Location: {$job->location}" : null,
            filled($job->type) ? "Type: {$job->type}" : null,
        ]));
    }

    /**
     * @return array<int, float>
     */
    private function profileEmbedding(CandidateProfile $profile): array
    {
        return Cache::remember(
            'ai:profile-embedding:'.config('ai.embedding_model').':'.$profile->id.':'.optional($profile->updated_at)->timestamp,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->ai->embed($this->profileText($profile)),
        );
    }

    /**
     * Reuse the embedding already stored on the job (from the Phase 14
     * pipeline) when present; only embed on the fly if the queue hasn't
     * produced one yet.
     *
     * @return array<int, float>
     */
    private function jobEmbedding(Job $job): array
    {
        if (is_array($job->embedding) && $job->embedding !== []) {
            return $job->embedding;
        }

        return Cache::remember(
            'ai:job-embedding:'.config('ai.embedding_model').':'.$job->id.':'.optional($job->updated_at)->timestamp,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->ai->embed($this->jobText($job)),
        );
    }

    private function cacheKey(CandidateProfile $profile, Job $job): string
    {
        return implode(':', [
            'ai:match',
            config('ai.embedding_model'),
            config('ai.chat_model'),
            $profile->id,
            optional($profile->updated_at)->timestamp,
            $job->id,
            optional($job->updated_at)->timestamp,
        ]);
    }
}
