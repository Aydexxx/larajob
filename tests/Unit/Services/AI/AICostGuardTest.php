<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AICostGuard;
use Tests\TestCase;

/**
 * Unit coverage for the cost guard's three jobs: the soft global daily
 * budget, per-user daily feature caps, and the CV re-parse debounce. All
 * state is cache/RateLimiter backed (array store in tests) and resets per
 * test via the fresh application.
 */
class AICostGuardTest extends TestCase
{
    private function guard(): AICostGuard
    {
        return $this->app->make(AICostGuard::class);
    }

    public function test_budget_is_unlimited_when_the_limit_is_zero(): void
    {
        config(['ai.budget.daily_call_limit' => 0]);
        $guard = $this->guard();

        foreach (range(1, 50) as $i) {
            $guard->record('embedding');
        }

        $this->assertTrue($guard->withinBudget());
    }

    public function test_budget_flips_off_once_the_daily_limit_is_reached(): void
    {
        config(['ai.budget.daily_call_limit' => 3]);
        $guard = $this->guard();

        $this->assertTrue($guard->withinBudget());

        $guard->record('embedding');
        $guard->record('ask');
        $this->assertTrue($guard->withinBudget(), 'Two of three used — still within budget.');

        $guard->record('ask');
        $this->assertFalse($guard->withinBudget(), 'Third call reaches the limit — budget exhausted.');
    }

    public function test_record_tracks_per_feature_and_total_counts(): void
    {
        $guard = $this->guard();

        $guard->record('ask');
        $guard->record('ask');
        $guard->record('embedding');

        $this->assertSame(2, $guard->callsToday('ask'));
        $this->assertSame(1, $guard->callsToday('embedding'));
        $this->assertSame(3, $guard->totalToday());
    }

    public function test_per_user_cap_allows_up_to_the_limit_then_blocks(): void
    {
        config(['ai.limits.ask.per_day' => 2]);
        $guard = $this->guard();

        $this->assertTrue($guard->allow('ask'));
        $guard->hit('ask');
        $this->assertTrue($guard->allow('ask'));
        $guard->hit('ask');

        $this->assertFalse($guard->allow('ask'), 'Third attempt is over the daily cap.');
    }

    public function test_features_without_a_configured_cap_are_unlimited(): void
    {
        $guard = $this->guard();

        foreach (range(1, 50) as $i) {
            $guard->hit('embedding');
        }

        $this->assertTrue($guard->allow('embedding'));
    }

    public function test_resume_parse_is_debounced_per_profile_and_file(): void
    {
        config(['ai.limits.cv-parse.debounce_minutes' => 10]);
        $guard = $this->guard();

        $this->assertFalse($guard->resumeParseDebounced(1, 'hash-a'));

        $guard->markResumeParsed(1, 'hash-a');

        $this->assertTrue($guard->resumeParseDebounced(1, 'hash-a'), 'Same file, same profile → debounced.');
        $this->assertFalse($guard->resumeParseDebounced(1, 'hash-b'), 'A different file is not debounced.');
        $this->assertFalse($guard->resumeParseDebounced(2, 'hash-a'), 'A different profile is not debounced.');
    }

    public function test_resume_debounce_is_disabled_when_the_window_is_zero(): void
    {
        config(['ai.limits.cv-parse.debounce_minutes' => 0]);
        $guard = $this->guard();

        $guard->markResumeParsed(1, 'hash-a');

        $this->assertFalse($guard->resumeParseDebounced(1, 'hash-a'));
    }
}
