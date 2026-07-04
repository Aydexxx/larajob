<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;

/**
 * Central cost-control for the AI layer. Three concerns, one place:
 *
 *  1. A soft GLOBAL daily budget. Every real model call is recorded here
 *     (from {@see AIService::measured()}); once the day's total reaches
 *     ai.budget.daily_call_limit, {@see withinBudget()} returns false and
 *     AIService::isEnabled() flips off — so features degrade to their
 *     rule-based fallbacks instead of spending more. Fail safe, not fail
 *     expensive.
 *
 *  2. PER-USER daily caps per feature (ai.limits.*). Feature services call
 *     {@see allow()} before a model call and {@see hit()} after a successful
 *     one; over the cap, the caller returns its existing fallback. Keyed by
 *     the authenticated user, or the request IP for public endpoints.
 *
 *  3. Per-feature usage counters for observability, and a same-file debounce
 *     for CV parsing.
 *
 * Everything is cache/RateLimiter backed and approximate by design — this is
 * a spend guard, not an accounting ledger. Counters reset daily.
 */
class AICostGuard
{
    private const DAY_SECONDS = 86400;

    /**
     * Whether the global daily budget still has headroom. True when the
     * guard is disabled (limit <= 0).
     */
    public function withinBudget(): bool
    {
        $limit = $this->dailyBudget();

        return $limit <= 0 || $this->totalToday() < $limit;
    }

    /**
     * Record one real model call against the global total and the feature's
     * counter, and log the running tallies so spend is observable per feature.
     * Called once per outbound call at the AIService choke point.
     */
    public function record(string $feature): void
    {
        $totalToday = $this->increment($this->totalKey());
        $featureToday = $this->increment($this->featureKey($feature));

        Log::channel('ai')->info('AI call recorded', [
            'feature' => $feature,
            'feature_calls_today' => $featureToday,
            'total_calls_today' => $totalToday,
            'daily_budget' => $this->dailyBudget(),
        ]);
    }

    public function totalToday(): int
    {
        return (int) Cache::get($this->totalKey(), 0);
    }

    public function callsToday(string $feature): int
    {
        return (int) Cache::get($this->featureKey($feature), 0);
    }

    /**
     * Whether the current actor may make another call to $feature today.
     * True when the feature has no configured cap.
     */
    public function allow(string $feature): bool
    {
        $perDay = $this->perDay($feature);

        return $perDay <= 0 || ! RateLimiter::tooManyAttempts($this->capKey($feature), $perDay);
    }

    /**
     * Count one successful call against the current actor's daily cap for
     * $feature. No-op for uncapped features.
     */
    public function hit(string $feature): void
    {
        if ($this->perDay($feature) > 0) {
            RateLimiter::hit($this->capKey($feature), self::DAY_SECONDS);
        }
    }

    /**
     * Has an identical resume file for this profile already been parsed
     * within the debounce window? Read-only — call {@see markResumeParsed()}
     * when a parse is actually dispatched.
     */
    public function resumeParseDebounced(int $profileId, string $fileHash): bool
    {
        return $this->resumeDebounceMinutes() > 0
            && Cache::has($this->resumeDebounceKey($profileId, $fileHash));
    }

    /**
     * Remember that this exact file was just parsed, so an immediate
     * re-upload of the same file is skipped for the debounce window.
     */
    public function markResumeParsed(int $profileId, string $fileHash): void
    {
        $minutes = $this->resumeDebounceMinutes();

        if ($minutes > 0) {
            Cache::put($this->resumeDebounceKey($profileId, $fileHash), true, now()->addMinutes($minutes));
        }
    }

    private function dailyBudget(): int
    {
        return (int) config('ai.budget.daily_call_limit', 0);
    }

    private function perDay(string $feature): int
    {
        return (int) config("ai.limits.{$feature}.per_day", 0);
    }

    private function resumeDebounceMinutes(): int
    {
        return (int) config('ai.limits.cv-parse.debounce_minutes', 0);
    }

    /**
     * Increment a daily counter, creating it (with a >1-day TTL so it spans
     * the day it counts) when absent — some cache stores won't increment a
     * missing key otherwise.
     */
    private function increment(string $key): int
    {
        Cache::add($key, 0, now()->addDay()->addHours(6));

        return (int) Cache::increment($key);
    }

    private function totalKey(): string
    {
        return 'ai:calls:'.$this->today().':total';
    }

    private function featureKey(string $feature): string
    {
        return 'ai:calls:'.$this->today().':feature:'.$feature;
    }

    private function capKey(string $feature): string
    {
        return 'ai-cap:'.$feature.':'.$this->actorKey();
    }

    private function resumeDebounceKey(int $profileId, string $fileHash): string
    {
        return 'ai:cv-parse:'.$profileId.':'.$fileHash;
    }

    /**
     * The actor a per-user cap is charged to: the authenticated user, or the
     * request IP for public endpoints (ask-about-job), or "system" for
     * queued/CLI work with neither.
     */
    private function actorKey(): string
    {
        return (string) (Auth::id() ?? Request::ip() ?? 'system');
    }

    private function today(): string
    {
        return Carbon::now()->toDateString();
    }
}
