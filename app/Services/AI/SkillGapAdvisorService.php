<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Repositories\JobMatchRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Suggests which skills would unlock the most "almost matched" roles for a
 * candidate — jobs the embedding ranks as a meaningful (medium-tier) fit but
 * not yet a strong one.
 *
 * Entirely rule-based: no LLM call, ever. It reuses two already-cached,
 * deterministic building blocks — {@see JobMatchRepository::cachedTopMatches()}
 * for the ranked candidate set and {@see MatchService::scoreFor()} for each
 * job's score — and adds one cheap step of its own: for each near-miss job,
 * which skills (drawn from the vocabulary of skills every candidate on the
 * platform has entered) appear in the posting but not on this profile.
 * Aggregating those across the near-miss set surfaces the highest-leverage
 * skills to learn next.
 */
class SkillGapAdvisorService
{
    /** Same boundary MatchResult/MatchExplanation use for "medium" — a role worth counting as a near miss. */
    private const NEAR_MISS_FLOOR = 50;

    /** Below this, the role is already a strong match — nothing to unlock. */
    private const NEAR_MISS_CEILING = 75;

    /** How many of the candidate's top-ranked (by embedding similarity) roles to sample for near-misses. */
    private const SAMPLE_SIZE = 40;

    /** How many skills to surface. */
    private const MAX_RECOMMENDATIONS = 5;

    /** The platform-wide skill vocabulary changes slowly; a day-long cache is cheap and plenty fresh. */
    private const VOCABULARY_CACHE_TTL_MINUTES = 1440;

    /**
     * Shares the near-miss job set's staleness bound (see
     * JobMatchRepository::FEED_CACHE_TTL_MINUTES) — this result is only ever
     * as fresh as that underlying ranking anyway.
     */
    private const RESULT_CACHE_TTL_MINUTES = 15;

    public function __construct(
        private readonly MatchService $matches,
        private readonly JobMatchRepository $repository,
    ) {}

    public function isAvailable(): bool
    {
        return $this->matches->isAvailable();
    }

    /**
     * Top skills to learn next, or an empty list when there's nothing
     * meaningful to show (AI off, profile not scorable/embedded yet, or no
     * near-miss roles at all — e.g. every ranked role is already a strong
     * match, or none are close).
     *
     * @return array<int, array{skill: string, jobCount: int}>
     */
    public function recommendationsFor(?CandidateProfile $profile): array
    {
        if (! $this->isAvailable() || ! $this->matches->profileIsScorable($profile) || blank($profile->embedding)) {
            return [];
        }

        return Cache::remember(
            $this->resultCacheKey($profile),
            now()->addMinutes(self::RESULT_CACHE_TTL_MINUTES),
            fn (): array => $this->compute($profile),
        );
    }

    /**
     * @return array<int, array{skill: string, jobCount: int}>
     */
    private function compute(CandidateProfile $profile): array
    {
        $nearMisses = $this->nearMissJobs($profile);

        if ($nearMisses->isEmpty()) {
            return [];
        }

        $vocabulary = $this->skillVocabulary();
        $ownSkills = $this->ownSkills($profile);

        $vocabulary = array_values(array_filter(
            $vocabulary,
            fn (string $skill): bool => ! in_array(Str::lower($skill), $ownSkills, true),
        ));

        if ($vocabulary === []) {
            return [];
        }

        $counts = [];

        foreach ($nearMisses as $job) {
            $text = Str::lower($this->jobText($job));

            foreach ($vocabulary as $skill) {
                if (self::textMentionsSkill($text, $skill)) {
                    $counts[$skill] = ($counts[$skill] ?? 0) + 1;
                }
            }
        }

        arsort($counts);

        return collect($counts)
            ->take(self::MAX_RECOMMENDATIONS)
            ->map(fn (int $count, string $skill): array => ['skill' => $skill, 'jobCount' => $count])
            ->values()
            ->all();
    }

    /**
     * The candidate's top-ranked roles by embedding similarity, filtered to
     * the medium tier — close enough to be worth improving, not so far off
     * that "learn one skill" wouldn't plausibly move the needle, and not
     * already a strong match with nothing to unlock.
     *
     * @return Collection<int, Job>
     */
    private function nearMissJobs(CandidateProfile $profile): Collection
    {
        return $this->repository->cachedTopMatches($profile, self::SAMPLE_SIZE)
            ->filter(function (Job $job) use ($profile): bool {
                $score = $this->matches->scoreFor($profile, $job);

                return $score >= self::NEAR_MISS_FLOOR && $score < self::NEAR_MISS_CEILING;
            })
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function ownSkills(CandidateProfile $profile): array
    {
        return collect(explode(',', (string) $profile->skills))
            ->map(fn (string $skill): string => Str::lower(trim($skill)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The distinct skills every candidate on the platform has entered on
     * their profile — used as the "known skill" dictionary to scan job
     * postings for, since job listings have no structured skills field of
     * their own. Cached globally (not per-candidate): it only grows as more
     * candidates fill in their profiles.
     *
     * @return array<int, string>
     */
    private function skillVocabulary(): array
    {
        return Cache::remember(
            'ai:skill-vocabulary',
            now()->addMinutes(self::VOCABULARY_CACHE_TTL_MINUTES),
            function (): array {
                return CandidateProfile::query()
                    ->whereNotNull('skills')
                    ->pluck('skills')
                    ->flatMap(fn (string $skills): array => explode(',', $skills))
                    ->map(fn (string $skill): string => trim($skill))
                    ->filter()
                    ->unique(fn (string $skill): string => Str::lower($skill))
                    ->values()
                    ->all();
            },
        );
    }

    private function jobText(Job $job): string
    {
        return implode("\n", array_filter([$job->title, $job->description, $job->requirements]));
    }

    /**
     * Whether $skill appears in $text as a whole token rather than a
     * substring — "Go" must not match inside "Google", "R" must not match
     * inside "your". A plain \b on both sides breaks for skills that start
     * or end with punctuation (".NET", "C++", "C#"), since \b only asserts a
     * boundary between a word and non-word character: if the skill's own
     * edge is already non-word, \b can never match there. So the boundary on
     * each side is conditional — \b where that edge is alphanumeric, a
     * negative lookaround (not preceded/followed by an alphanumeric) where
     * it isn't.
     */
    private static function textMentionsSkill(string $lowerText, string $skill): bool
    {
        $skill = Str::lower($skill);
        $quoted = preg_quote($skill, '/');

        $left = ctype_alnum($skill[0]) ? '\b' : '(?<![a-z0-9])';
        $right = ctype_alnum(substr($skill, -1)) ? '\b' : '(?![a-z0-9])';

        return (bool) preg_match('/'.$left.$quoted.$right.'/u', $lowerText);
    }

    private function resultCacheKey(CandidateProfile $profile): string
    {
        return implode(':', [
            'ai:skill-gap',
            $profile->id,
            $profile->embeddingVersion(),
            self::SAMPLE_SIZE,
        ]);
    }
}
