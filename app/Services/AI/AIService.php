<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\AIDisabledException;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider as PrismProvider;
use Prism\Prism\Prism;
use Throwable;

/**
 * The single entry point for every AI operation in LaraJob.
 *
 * All calls route through Prism using whatever provider config/ai.php has
 * selected. Feature code depends on the {@see AIProvider} contract and
 * never touches Prism or a provider name directly — switching providers
 * (none | openai | ollama) is purely a matter of configuration.
 *
 * Every call is timed and written to the dedicated "ai" log channel with
 * provider, model, latency and (when available) token usage.
 */
class AIService implements AIProvider
{
    public function __construct(
        private readonly Prism $prism,
        private readonly AICostGuard $guard,
    ) {}

    /**
     * Whether the AI layer is active AND still within the soft global daily
     * budget. When the budget is exhausted this reports false, so every
     * feature degrades to its rule-based fallback for the rest of the day
     * instead of spending more.
     */
    public function isEnabled(): bool
    {
        return (bool) config('ai.enabled', false) && $this->guard->withinBudget();
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text, string $feature = 'embedding'): array
    {
        $this->ensureEnabled('embed');

        $provider = $this->provider();
        $model = (string) config('ai.embedding_model');

        return $this->measured('embed', $provider->value, $model, $feature, function () use ($provider, $model, $text) {
            $response = $this->prism->embeddings()
                ->using($provider, $model)
                ->fromInput($text)
                ->asEmbeddings();

            $vector = $response->embeddings[0]->embedding ?? [];

            return [
                'result' => array_map('floatval', $vector),
                'tokens' => $response->usage->tokens,
            ];
        });
    }

    public function chat(string $prompt, ?string $system = null, string $feature = 'chat'): string
    {
        $this->ensureEnabled('chat');

        $provider = $this->provider();
        $model = (string) config('ai.chat_model');

        return $this->measured('chat', $provider->value, $model, $feature, function () use ($provider, $model, $prompt, $system) {
            $request = $this->prism->text()
                ->using($provider, $model)
                ->withPrompt($prompt);

            if (filled($system)) {
                $request->withSystemPrompt($system);
            }

            $response = $request->asText();

            return [
                'result' => $response->text,
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ];
        });
    }

    /**
     * Resolve the configured provider name into a Prism provider enum.
     */
    private function provider(): PrismProvider
    {
        return PrismProvider::from((string) config('ai.provider'));
    }

    private function ensureEnabled(string $operation): void
    {
        if (! $this->isEnabled()) {
            throw AIDisabledException::make($operation);
        }
    }

    /**
     * Run an AI operation, timing it and logging the outcome.
     *
     * The callback returns ['result' => mixed, 'tokens' => ?int]; only the
     * result is handed back to the caller.
     *
     * On success the call is recorded against the per-feature usage counters
     * and the global daily budget (see {@see AICostGuard}).
     *
     * @param  callable():array{result: mixed, tokens: ?int}  $operation
     */
    private function measured(string $type, string $provider, string $model, string $feature, callable $operation): mixed
    {
        $startedAt = microtime(true);

        try {
            $outcome = $operation();
        } catch (Throwable $e) {
            Log::channel('ai')->error('AI call failed', [
                'operation' => $type,
                'feature' => $feature,
                'provider' => $provider,
                'model' => $model,
                'latency_ms' => $this->elapsedMs($startedAt),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Count the spend (per-feature + global budget) before returning.
        $this->guard->record($feature);

        Log::channel('ai')->info('AI call completed', [
            'operation' => $type,
            'feature' => $feature,
            'provider' => $provider,
            'model' => $model,
            'latency_ms' => $this->elapsedMs($startedAt),
            'tokens' => $outcome['tokens'],
        ]);

        return $outcome['result'];
    }

    private function elapsedMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
