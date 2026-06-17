<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Exceptions\AIDisabledException;

/**
 * Provider-agnostic contract for all AI operations in LaraJob.
 *
 * Bind this interface to a concrete implementation in the service
 * container so features depend on the abstraction, never on a specific
 * provider or on Prism directly. Swapping the implementation (e.g. for a
 * fake in tests) is a one-line container rebind.
 */
interface AIProvider
{
    /**
     * Whether the AI layer is active and safe to call.
     *
     * Callers MUST check this before invoking embed()/chat().
     */
    public function isEnabled(): bool;

    /**
     * Generate an embedding vector for the given text.
     *
     * @return array<int, float> The embedding vector.
     *
     * @throws AIDisabledException When the AI layer is disabled.
     */
    public function embed(string $text): array;

    /**
     * Generate a chat/completion response for the given prompt.
     *
     * @param  string|null  $system  Optional system prompt for steering.
     *
     * @throws AIDisabledException When the AI layer is disabled.
     */
    public function chat(string $prompt, ?string $system = null): string;
}
