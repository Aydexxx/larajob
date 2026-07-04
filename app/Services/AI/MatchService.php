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

    /**
     * Explained matches stay warm for a week. Safe because the cache key
     * embeds the profile's embedding version and the job's version — a
     * stale entry can never be served for changed inputs, so a long TTL
     * only saves money.
     */
    private const EXPLAIN_CACHE_TTL_MINUTES = 10080;

    /** Score penalty when a parsed hard requirement (min years) is not met. */
    private const HARD_REQUIREMENT_PENALTY = 15;

    /** Small bonus when the candidate meets the stated minimum years. */
    private const MEETS_REQUIREMENT_BONUS = 5;

    private const MAX_STRENGTHS = 5;

    private const MAX_GAPS = 4;

    public function __construct(
        private readonly AIProvider $ai,
        private readonly VectorSearchContract $vectorSearch,
        private readonly AICostGuard $guard,
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

    /**
     * The explained match: deterministic score plus strengths and
     * actionable gaps. Works with EVERY provider setting — with AI enabled
     * the narrative comes from one LLM call; with AI_PROVIDER=none (or on
     * any model failure) it is built purely from the structured overlap
     * between profile and job, with no API call at all.
     *
     * Cost discipline: the full result is cached for a week, keyed by
     * (profile embedding version, job version) — see explainCacheKey() —
     * so a warm pair costs nothing and editing unrelated profile fields
     * (phone, LinkedIn) does not invalidate it.
     */
    public function explain(CandidateProfile $profile, Job $job): MatchExplanation
    {
        $data = Cache::remember(
            $this->explainCacheKey($profile, $job),
            now()->addMinutes(self::EXPLAIN_CACHE_TTL_MINUTES),
            fn (): array => $this->computeExplanation($profile, $job)->toArray(),
        );

        return MatchExplanation::fromArray($data);
    }

    /**
     * Read a previously computed explanation without ever computing one.
     * Used for the initial page render so it never blocks or spends.
     */
    public function explainCached(CandidateProfile $profile, Job $job): ?MatchExplanation
    {
        $data = Cache::get($this->explainCacheKey($profile, $job));

        return is_array($data) ? MatchExplanation::fromArray($data) : null;
    }

    /**
     * The deterministic match score (0-100) ONLY — no narrative, and never
     * an LLM call. This is what the match rings on job cards and the "For
     * You" feed render, so a page of N cards costs zero tokens.
     *
     * It reuses the exact same scoring as explain() (embedding similarity +
     * hard-requirement adjustments), so a card's ring and the job page's
     * explanation always show the same number. Cached cheaply by the same
     * (profile embedding version, job version) key space; if explain() has
     * already run for the pair, its score is reused with no recompute.
     */
    public function scoreFor(CandidateProfile $profile, Job $job): int
    {
        if ($cached = Cache::get($this->explainCacheKey($profile, $job))) {
            return is_array($cached) ? (int) ($cached['score'] ?? 0) : 0;
        }

        return Cache::remember(
            $this->scoreCacheKey($profile, $job),
            now()->addMinutes(self::EXPLAIN_CACHE_TTL_MINUTES),
            fn (): int => $this->explainedScore($profile, $job, $this->structuredOverlap($profile, $job)),
        );
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
        // Per-user daily cap: once spent, degrade to the embedding-only
        // summary for the rest of the day instead of calling the model.
        if (! $this->ai->isEnabled() || ! $this->guard->allow('match-explain')) {
            return [$this->fallbackSummary($percentage), [], []];
        }

        try {
            $raw = $this->ai->chat($this->prompt($profile, $job, $percentage), $this->systemPrompt(), 'match-explain');
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Match narrative call failed; using fallback', [
                'profile_id' => $profile->id,
                'job_id' => $job->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [$this->fallbackSummary($percentage), [], []];
        }

        $this->guard->hit('match-explain');

        $parsed = $this->parseJson($raw);

        if ($parsed !== null) {
            return [$parsed['summary'], $parsed['strengths'], $parsed['gaps']];
        }

        Log::channel('ai')->warning('Match narrative could not be parsed; using fallback', [
            'profile_id' => $profile->id,
            'job_id' => $job->id,
        ]);

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

    private function computeExplanation(CandidateProfile $profile, Job $job): MatchExplanation
    {
        $overlap = $this->structuredOverlap($profile, $job);
        $score = $this->explainedScore($profile, $job, $overlap);

        // Model narrative only when AI is on AND the user is within their
        // daily cap; otherwise the rule-based explanation below (no API call).
        if ($this->ai->isEnabled() && $this->guard->allow('match-explain')) {
            $narrative = $this->modelNarrative($profile, $job, $score, $overlap);

            if ($narrative !== null) {
                return $this->withBreakdown(new MatchExplanation(
                    score: $score,
                    summary: $narrative['summary'],
                    strengths: $narrative['strengths'],
                    gaps: $narrative['gaps'],
                    source: MatchExplanation::SOURCE_MODEL,
                ), $overlap);
            }
        }

        return $this->withBreakdown($this->ruleBasedExplanation($score, $overlap), $overlap);
    }

    /**
     * Attach the deterministic, factual breakdown (matched/unmatched skills,
     * experience comparison) to an explanation. The matched set starts from
     * the structured overlap (skills literally present in the posting) and is
     * then reconciled with the narrative: any profile skill the strengths
     * cite is promoted to matched. That closes the gap the demo exposed — a
     * strength narrating "your Java and SQL are relevant" while the counter
     * read "0 of N matched" — so the chips and the written explanation can
     * never contradict each other. Costs nothing: both inputs are already
     * computed.
     *
     * @param  array{profileSkills: array<int, string>, matchedSkills: array<int, string>, requiredYears: ?int, experienceYears: ?int}  $overlap
     */
    private function withBreakdown(MatchExplanation $explanation, array $overlap): MatchExplanation
    {
        $matched = $this->reconcileMatchedSkills(
            $overlap['profileSkills'],
            $overlap['matchedSkills'],
            $explanation->strengths,
        );

        return new MatchExplanation(
            score: $explanation->score,
            summary: $explanation->summary,
            strengths: $explanation->strengths,
            gaps: $explanation->gaps,
            source: $explanation->source,
            matchedSkills: $matched,
            unmatchedSkills: array_values(array_diff($overlap['profileSkills'], $matched)),
            requiredYears: $overlap['requiredYears'],
            experienceYears: $overlap['experienceYears'],
        );
    }

    /**
     * The matched-skill set the breakdown shows: every profile skill that is
     * EITHER literally present in the posting OR named in the narrative
     * strengths. Returned in profile order.
     *
     * Reconciling against the strengths guarantees the invariant the UI
     * depends on — if the explanation praises a skill, that skill is counted
     * as matched — so the "X of N matched" chips never undercount what the
     * prose is claiming. Matching mirrors structuredOverlap()'s case-
     * insensitive substring test, so the two stay consistent.
     *
     * @param  array<int, string>  $profileSkills
     * @param  array<int, string>  $matchedSkills
     * @param  array<int, string>  $strengths
     * @return array<int, string>
     */
    private function reconcileMatchedSkills(array $profileSkills, array $matchedSkills, array $strengths): array
    {
        $alreadyMatched = array_map(fn (string $skill): string => Str::lower($skill), $matchedSkills);
        $strengthText = Str::lower(implode("\n", $strengths));

        return array_values(array_filter(
            $profileSkills,
            function (string $skill) use ($alreadyMatched, $strengthText): bool {
                $needle = Str::lower($skill);

                return in_array($needle, $alreadyMatched, true)
                    || ($needle !== '' && str_contains($strengthText, $needle));
            },
        ));
    }

    /**
     * Deterministic 0-100 score: an embedding-similarity base plus
     * rule-based adjustments for parsed hard requirements. The base prefers
     * stored vectors (free); only when AI is enabled and a vector is
     * missing is one embedded on the fly. With no vectors available at all
     * (AI_PROVIDER=none before any backfill) the base degrades to the
     * structured skill-overlap ratio, so a meaningful score always exists.
     *
     * @param  array{profileSkills: array<int, string>, matchedSkills: array<int, string>, requiredYears: ?int, experienceYears: ?int}  $overlap
     */
    private function explainedScore(CandidateProfile $profile, Job $job, array $overlap): int
    {
        $base = $this->embeddingBase($profile, $job) ?? $this->overlapBase($overlap);

        if ($overlap['requiredYears'] !== null && $overlap['experienceYears'] !== null) {
            $base += $overlap['experienceYears'] < $overlap['requiredYears']
                ? -self::HARD_REQUIREMENT_PENALTY
                : self::MEETS_REQUIREMENT_BONUS;
        }

        return max(0, min(100, $base));
    }

    /**
     * Cosine base from embeddings, or null when no vectors can be had
     * without spending (AI disabled and nothing stored).
     */
    private function embeddingBase(CandidateProfile $profile, Job $job): ?int
    {
        $profileVector = $this->storedVector($profile->embedding)
            ?? ($this->ai->isEnabled() ? $this->profileEmbedding($profile) : null);
        $jobVector = $this->storedVector($job->embedding)
            ?? ($this->ai->isEnabled() ? $this->jobEmbedding($job) : null);

        if (blank($profileVector) || blank($jobVector)) {
            return null;
        }

        $cosine = $this->vectorSearch->cosineSimilarity($profileVector, $jobVector);

        return (int) round(max(0.0, min(1.0, $cosine)) * 100);
    }

    /**
     * @param  array{profileSkills: array<int, string>, matchedSkills: array<int, string>}  $overlap
     */
    private function overlapBase(array $overlap): int
    {
        if ($overlap['profileSkills'] === []) {
            return 0;
        }

        return (int) round(count($overlap['matchedSkills']) / count($overlap['profileSkills']) * 100);
    }

    /**
     * @return array<int, float>|null
     */
    private function storedVector(mixed $embedding): ?array
    {
        return (is_array($embedding) && $embedding !== []) ? $embedding : null;
    }

    /**
     * The structured, deterministic facts both narrative paths (and the
     * score adjustments) are built from: which of the candidate's skills
     * appear in the job posting, and how their experience compares to the
     * job's stated minimum.
     *
     * @return array{profileSkills: array<int, string>, matchedSkills: array<int, string>, requiredYears: ?int, experienceYears: ?int}
     */
    private function structuredOverlap(CandidateProfile $profile, Job $job): array
    {
        $profileSkills = collect(preg_split('/,/', (string) $profile->skills) ?: [])
            ->map(fn (string $skill): string => trim($skill))
            ->filter()
            ->unique(fn (string $skill): string => Str::lower($skill))
            ->values();

        $jobText = Str::lower(implode("\n", array_filter([
            $job->title, $job->description, $job->requirements,
        ])));

        return [
            'profileSkills' => $profileSkills->all(),
            'matchedSkills' => $profileSkills
                ->filter(fn (string $skill): bool => str_contains($jobText, Str::lower($skill)))
                ->values()
                ->all(),
            'requiredYears' => $this->requiredYears($job),
            'experienceYears' => $profile->experience_years,
        ];
    }

    /**
     * Parse a minimum-years hard requirement from the job's own wording
     * ("5+ years", "at least 3 years", "minimum of 4 years"). Null when the
     * posting states none.
     */
    private function requiredYears(Job $job): ?int
    {
        $text = implode("\n", array_filter([$job->requirements, $job->description]));

        $patterns = [
            '/(\d{1,2})\s*\+\s*years?/i',
            '/at least\s+(\d{1,2})\s+years?/i',
            '/minimum(?:\s+of)?\s+(\d{1,2})\s+years?/i',
            '/(\d{1,2})\s+or more\s+years?/i',
        ];

        $required = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $required = max($required ?? 0, (int) $matches[1]);
            }
        }

        return $required;
    }

    /**
     * One LLM call for the explained narrative, constrained to strict JSON
     * and parsed defensively. Null on any failure — the caller degrades to
     * the rule-based narrative instead of retrying (cost discipline).
     *
     * @param  array{profileSkills: array<int, string>, matchedSkills: array<int, string>, requiredYears: ?int, experienceYears: ?int}  $overlap
     * @return array{summary: string, strengths: array<int, string>, gaps: array<int, array{gap: string, suggestion: string}>}|null
     */
    private function modelNarrative(CandidateProfile $profile, Job $job, int $score, array $overlap): ?array
    {
        try {
            $raw = $this->ai->chat($this->explainPrompt($profile, $job, $score, $overlap), $this->explainSystemPrompt(), 'match-explain');
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Match explanation call failed; using rule-based narrative', [
                'profile_id' => $profile->id,
                'job_id' => $job->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        // The call was made — charge it to the user's daily cap.
        $this->guard->hit('match-explain');

        $parsed = $this->parseExplanationJson($raw);

        if ($parsed === null) {
            Log::channel('ai')->warning('Match explanation could not be parsed; using rule-based narrative', [
                'profile_id' => $profile->id,
                'job_id' => $job->id,
            ]);
        }

        return $parsed;
    }

    /**
     * Extract and validate the explanation shape from raw model output.
     * Same brace-slicing tolerance as parseJson(), but gaps stay
     * structured ({gap, suggestion}) instead of being coerced to strings.
     * A bare-string gap from a sloppy model is tolerated (empty
     * suggestion) rather than dropped.
     *
     * @return array{summary: string, strengths: array<int, string>, gaps: array<int, array{gap: string, suggestion: string}>}|null
     */
    private function parseExplanationJson(string $raw): ?array
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
            'gaps' => array_slice(
                MatchExplanation::fromArray(['gaps' => $decoded['gaps'] ?? []])->gaps,
                0,
                self::MAX_GAPS,
            ),
        ];
    }

    /**
     * Narrative built purely from the structured overlap — no API call.
     * Every gap comes with a short, actionable suggestion.
     *
     * @param  array{profileSkills: array<int, string>, matchedSkills: array<int, string>, requiredYears: ?int, experienceYears: ?int}  $overlap
     */
    private function ruleBasedExplanation(int $score, array $overlap): MatchExplanation
    {
        $strengths = [];
        $gaps = [];

        if ($overlap['matchedSkills'] !== []) {
            $strengths[] = 'Skills from your profile that appear in this posting: '
                .implode(', ', array_slice($overlap['matchedSkills'], 0, self::MAX_STRENGTHS)).'.';
        }

        $meetsYears = $overlap['requiredYears'] !== null
            && $overlap['experienceYears'] !== null
            && $overlap['experienceYears'] >= $overlap['requiredYears'];

        if ($meetsYears) {
            $strengths[] = sprintf(
                'You meet the stated minimum of %d years of experience (your profile lists %d).',
                $overlap['requiredYears'],
                $overlap['experienceYears'],
            );
        }

        if ($overlap['requiredYears'] !== null && $overlap['experienceYears'] !== null && ! $meetsYears) {
            $gaps[] = [
                'gap' => sprintf(
                    'The role asks for %d+ years of experience; your profile lists %d.',
                    $overlap['requiredYears'],
                    $overlap['experienceYears'],
                ),
                'suggestion' => 'Highlight projects, freelance or open-source work in your bio that demonstrates equivalent depth.',
            ];
        }

        if ($overlap['requiredYears'] !== null && $overlap['experienceYears'] === null) {
            $gaps[] = [
                'gap' => sprintf('The role asks for %d+ years of experience, but your profile does not state yours.', $overlap['requiredYears']),
                'suggestion' => 'Add your years of experience to your profile so it can be matched against requirements.',
            ];
        }

        $matched = count($overlap['matchedSkills']);
        $total = count($overlap['profileSkills']);

        if ($total > 0 && $matched < $total) {
            $gaps[] = [
                'gap' => sprintf('Only %d of the %d skills on your profile appear in this posting.', $matched, $total),
                'suggestion' => 'Re-read the requirements and add any of those skills you already have to your profile.',
            ];
        }

        return new MatchExplanation(
            score: $score,
            summary: $this->fallbackSummary($score),
            strengths: array_slice($strengths, 0, self::MAX_STRENGTHS),
            gaps: array_slice($gaps, 0, self::MAX_GAPS),
            source: MatchExplanation::SOURCE_RULES,
        );
    }

    private function explainSystemPrompt(): string
    {
        return implode(' ', [
            'You explain how well a job candidate fits a role.',
            'Respond with a SINGLE valid JSON object and nothing else — no prose, no markdown, no code fences.',
            'Shape: {"summary": string, "strengths": string[], "gaps": [{"gap": string, "suggestion": string}]}.',
            'summary is one or two concise sentences.',
            'strengths lists concrete reasons the candidate fits (max 5 short items).',
            'gaps lists concrete missing or weaker areas (max 4); each item pairs the gap with one short, actionable suggestion the candidate could take to close it.',
            'Do not invent facts that are not supported by the inputs, and do not contradict the provided score or overlap facts.',
        ]);
    }

    /**
     * @param  array{profileSkills: array<int, string>, matchedSkills: array<int, string>, requiredYears: ?int, experienceYears: ?int}  $overlap
     */
    private function explainPrompt(CandidateProfile $profile, Job $job, int $score, array $overlap): string
    {
        return implode("\n", [
            "A deterministic match score of {$score}% has already been computed for this pair — explain it, do not re-score.",
            '',
            '== VERIFIED OVERLAP FACTS ==',
            'Candidate skills found in the posting: '.($overlap['matchedSkills'] === [] ? '(none)' : implode(', ', $overlap['matchedSkills'])),
            'Minimum years required by the posting: '.($overlap['requiredYears'] ?? 'not stated'),
            'Candidate years of experience: '.($overlap['experienceYears'] ?? 'not stated'),
            '',
            '== CANDIDATE PROFILE ==',
            $this->profileText($profile),
            '',
            '== JOB ==',
            $this->jobText($job),
        ]);
    }

    /**
     * Cache key for explained matches — THE cost-control mechanism.
     *
     * profile_version is the profile's embedding timestamp: it only moves
     * when the embedding was regenerated, i.e. when a field that feeds
     * matching changed (see CandidateProfileObserver). Editing phone or
     * LinkedIn does not invalidate. Before any embedding exists (e.g.
     * AI_PROVIDER=none pre-backfill) it falls back to updated_at, which
     * over-invalidates but only ever re-runs the free rule-based path.
     * The job side uses updated_at; the provider/models are included so a
     * provider switch regenerates rather than serving stale narratives.
     */
    private function explainCacheKey(CandidateProfile $profile, Job $job): string
    {
        return implode(':', [
            'ai:match-explain',
            config('ai.provider'),
            config('ai.embedding_model'),
            config('ai.chat_model'),
            $profile->id,
            $profile->embeddingVersion(),
            $job->id,
            optional($job->updated_at)->timestamp ?? 0,
        ]);
    }

    /**
     * Cache key for the score-only value. Shares the same versioning as
     * explainCacheKey() (so it invalidates on the same signals) but omits
     * the chat model — the score never depends on the narrative provider.
     */
    private function scoreCacheKey(CandidateProfile $profile, Job $job): string
    {
        return implode(':', [
            'ai:match-score',
            config('ai.embedding_model'),
            $profile->id,
            $profile->embeddingVersion(),
            $job->id,
            optional($job->updated_at)->timestamp ?? 0,
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
     * Reuse the embedding stored on the profile (from the async pipeline)
     * when present; only embed on the fly if none has been generated yet.
     *
     * @return array<int, float>
     */
    private function profileEmbedding(CandidateProfile $profile): array
    {
        if (is_array($profile->embedding) && $profile->embedding !== []) {
            return $profile->embedding;
        }

        return Cache::remember(
            'ai:profile-embedding:'.config('ai.embedding_model').':'.$profile->id.':'.optional($profile->updated_at)->timestamp,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->ai->embed($this->profileText($profile), 'embedding'),
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
            fn (): array => $this->ai->embed($this->jobText($job), 'embedding'),
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
