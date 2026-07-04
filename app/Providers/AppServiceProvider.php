<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use App\Policies\ApplicationPolicy;
use App\Policies\JobPolicy;
use App\Services\AI\AICostGuard;
use App\Services\AI\AIService;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Contracts\EmbeddingProvider;
use App\Services\AI\Contracts\VectorSearch as VectorSearchContract;
use App\Services\AI\EmbeddingService;
use App\Services\AI\VectorSearch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Central AI cost guard (soft global budget, per-user daily caps,
        // usage counters, CV-parse debounce). Singleton so the whole request
        // shares one view of the counters.
        $this->app->singleton(AICostGuard::class);

        // Single, swappable entry point for all AI operations. Bind the
        // contract to the Prism-backed implementation so features depend on
        // the abstraction; rebinding AIProvider (e.g. in tests) swaps it.
        $this->app->singleton(AIService::class);
        $this->app->bind(AIProvider::class, AIService::class);

        // Ranks candidates by embedding similarity. Swappable for a
        // pgvector/Pinecone-backed implementation later — see
        // VectorSearchContract for why callers depend on the interface.
        $this->app->bind(VectorSearchContract::class, VectorSearch::class);

        // Embedding vectors for semantic search. Always yields a vector:
        // real provider output when AI is enabled, a deterministic stub
        // when AI_PROVIDER=none — so the pgvector pipeline runs end-to-end
        // without an API key.
        $this->app->bind(EmbeddingProvider::class, EmbeddingService::class);
    }

    public function boot(): void
    {
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);

        // Platform-wide administrative gate — the second layer of defense
        // behind the role:admin route middleware. Used to guard destructive
        // admin actions (suspend, delete, verify, force-close).
        Gate::define('manage-platform', fn (User $user) => $user->isAdmin());

        // Shared budget for the AI draft-generation endpoints (candidate
        // cover letter, employer job description) — caps LLM spend per user
        // regardless of which draft feature they're using.
        RateLimiter::for('ai-draft', fn (Request $request) => Limit::perMinute(5)->by($request->user()->id));

        // "Ask about this role" chat is public/unauthenticated, so the
        // budget is keyed by IP rather than user id.
        RateLimiter::for('ai-ask', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // On-demand match explanations / applicant summaries. Per-minute
        // burst guard keyed by user; the graceful per-day cap lives in
        // MatchService via AICostGuard (config: ai.limits.match-explain).
        RateLimiter::for('ai-explain', fn (Request $request) => Limit::perMinute(20)->by($request->user()->id));
    }
}
