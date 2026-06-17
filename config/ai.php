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
    | Per-Provider Model Reference
    |--------------------------------------------------------------------------
    |
    | Kept for visibility / tooling. The active provider's models are
    | promoted to the top-level keys above.
    |
    */

    'models' => $models,

];
