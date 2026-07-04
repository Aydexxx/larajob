<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Prompts\ResumeParsePrompt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns raw resume text into a structured {@see ResumeParseResult}.
 *
 * Provider-agnostic through {@see AIProvider}: with openai/ollama the text
 * goes to the configured chat model constrained to strict JSON; with
 * AI_PROVIDER=none no call is made and the empty structured result comes
 * back, so the upload → parse → review flow runs end-to-end without a key.
 *
 * Model output is never trusted. Anything unusable — an exception from the
 * provider, malformed JSON, wrong types — degrades to the empty result, so
 * callers can always store what they get and the live profile is never
 * touched by this service (applying a suggestion is a separate, explicit
 * user action).
 */
class ResumeParserService
{
    public function __construct(
        private readonly AIProvider $ai,
        private readonly ResumeParsePrompt $prompt,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    public function parse(string $resumeText): ResumeParseResult
    {
        if (! $this->ai->isEnabled() || trim($resumeText) === '') {
            return ResumeParseResult::empty();
        }

        try {
            $raw = $this->ai->chat($this->prompt->prompt($resumeText), $this->prompt->system(), 'cv-parse');
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Resume parse call failed; returning empty result', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ResumeParseResult::empty();
        }

        $decoded = $this->decode($raw);

        if ($decoded === null) {
            Log::channel('ai')->warning('Resume parse output could not be decoded; returning empty result');

            return ResumeParseResult::empty();
        }

        return ResumeParseResult::fromArray($decoded);
    }

    /**
     * Extract a JSON object from raw model output. Tolerates surrounding
     * prose / markdown fences by slicing to the outermost braces (same
     * defensive approach as MatchService). Returns null if nothing usable.
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
