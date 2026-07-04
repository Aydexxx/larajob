<?php

/*
|--------------------------------------------------------------------------
| AI Layer Configuration
|--------------------------------------------------------------------------
|
| LaraJob talks to LLM providers through a single, provider-agnostic
| service (App\Services\AI\AIService) built on top of Prism. This file is
| the ONLY place that decides which provider is active and which models
| are used. Switching providers is config-only — no feature code changes.
|
| The default provider is "none", which keeps the entire AI layer disabled
| so the application boots and runs without any API key or external model.
|
*/

$provider = env('AI_PROVIDER', 'none');

$models = [
    'openai' => [
        'chat_model' => env('AI_OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('AI_OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],
    'ollama' => [
        'chat_model' => env('AI_OLLAMA_CHAT_MODEL', 'llama3.2'),
        'embedding_model' => env('AI_OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    ],
];

/*
| "enabled" is computed here so callers never have to re-derive it. It is
| true only when a real provider is selected AND the credentials that
| provider needs are actually present. With AI_PROVIDER=none it is always
| false. Credentials live in config/prism.php (Prism owns the transport).
*/
$enabled = match ($provider) {
    'openai' => filled(env('OPENAI_API_KEY')),
    'ollama' => filled(env('OLLAMA_URL', 'http://localhost:11434')),
    default => false,
};

return [

    /*
    |--------------------------------------------------------------------------
    | Active Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "none" | "openai" | "ollama".
    |
    */

    'provider' => $provider,

    /*
    |--------------------------------------------------------------------------
    | Computed Enabled Flag
    |--------------------------------------------------------------------------
    |
    | Read this (or AIService::isEnabled()) before invoking any AI feature.
    | Never rely on exceptions for control flow.
    |
    */

    'enabled' => $enabled,

    /*
    |--------------------------------------------------------------------------
    | Models For The Active Provider
    |--------------------------------------------------------------------------
    |
    | Resolved up-front for the selected provider so the service does not
    | have to branch on the provider name when picking a model.
    |
    */

    'chat_model' => $models[$provider]['chat_model'] ?? null,
    'embedding_model' => $models[$provider]['embedding_model'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | Embedding Dimensions
    |--------------------------------------------------------------------------
    |
    | The dimensionality of stored embedding vectors. Must match both the
    | active embedding model (text-embedding-3-small = 1536) and the
    | vector(N) columns created by the pgvector migrations — changing this
    | after embeddings exist requires a re-embed and a column migration.
    | Stub vectors (AI_PROVIDER=none) are generated at this size too.
    |
    */

    'embedding_dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Per-Provider Model Reference
    |--------------------------------------------------------------------------
    |
    | Kept for visibility / tooling. The active provider's models are
    | promoted to the top-level keys above.
    |
    */

    'models' => $models,

    /*
    |--------------------------------------------------------------------------
    | Soft Global Budget Guard
    |--------------------------------------------------------------------------
    |
    | A fail-safe ceiling on real model calls per calendar day across the
    | whole application. Once the day's recorded call count reaches this
    | number, AIService::isEnabled() reports false and every feature silently
    | degrades to its rule-based / none-provider fallback instead of spending
    | more — fail safe, not fail expensive. Counting is approximate (cache
    | backed) and resets daily.
    |
    | 0 (the default) disables the guard entirely: unlimited calls.
    |
    */

    'budget' => [
        'daily_call_limit' => (int) env('AI_DAILY_CALL_BUDGET', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-User Daily Feature Caps
    |--------------------------------------------------------------------------
    |
    | Maximum real model calls a single actor (authenticated user, or IP for
    | public endpoints) may trigger per feature per day. Enforced by
    | AICostGuard; exceeding a cap degrades that feature to its rule-based
    | fallback for the rest of the day rather than erroring. These are the
    | daily companion to the per-minute burst limiters in AppServiceProvider.
    |
    | The array key is the feature label used everywhere (rate-limit key,
    | usage counter, and the "feature" tag on each logged AI call). A missing
    | or 0 `per_day` means that feature is uncapped (e.g. system embeddings).
    |
    | cv-parse additionally carries `debounce_minutes`: re-parsing the exact
    | same resume file within that window is skipped.
    |
    */

    'limits' => [
        'match-explain'   => ['per_day' => (int) env('AI_CAP_MATCH_EXPLAIN', 200)],
        'ask'             => ['per_day' => (int) env('AI_CAP_ASK', 100)],
        'cover-letter'    => ['per_day' => (int) env('AI_CAP_COVER_LETTER', 30)],
        'job-description' => ['per_day' => (int) env('AI_CAP_JOB_DESCRIPTION', 50)],
        'bias-check'      => ['per_day' => (int) env('AI_CAP_BIAS_CHECK', 100)],
        'cv-parse'        => [
            'per_day' => (int) env('AI_CAP_CV_PARSE', 10),
            'debounce_minutes' => (int) env('AI_CV_PARSE_DEBOUNCE_MINUTES', 10),
        ],
    ],

];
