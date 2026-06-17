<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an AI operation is attempted while the AI layer is disabled
 * (AI_PROVIDER=none, or the selected provider is missing credentials).
 *
 * This is a safety net, not a control-flow mechanism. Callers MUST check
 * AIService::isEnabled() before invoking embed()/chat(); hitting this
 * exception means that contract was violated.
 */
class AIDisabledException extends RuntimeException
{
    public static function make(string $operation): self
    {
        return new self(
            "AI operation [{$operation}] was attempted while the AI layer is disabled. ".
            'Check AIService::isEnabled() before calling AI features (current provider: '.
            config('ai.provider').').'
        );
    }
}
