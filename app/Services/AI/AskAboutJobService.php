<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Prompts\AskAboutJobPrompt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Answers candidate questions about a single job listing, grounded ONLY in
 * the listing + company context supplied by {@see AskAboutJobPrompt} — no
 * outside knowledge. Provider-agnostic: with a real provider the answer
 * comes from one chat call; with AI_PROVIDER=none this never calls out at
 * all and returns a fixed, honest message instead of pretending to answer.
 */
class AskAboutJobService
{
    /** ~6 turns of history (one user + one assistant message per turn). */
    private const MAX_HISTORY_MESSAGES = 12;

    private const DISABLED_MESSAGE = 'AI chat requires the AI provider to be enabled.';

    private const FAILURE_MESSAGE = 'Something went wrong answering that — please try again.';

    private const RATE_LIMITED_MESSAGE = "You've reached today's limit for questions about this role — please try again tomorrow.";

    public function __construct(
        private readonly AIProvider $ai,
        private readonly AskAboutJobPrompt $prompt,
        private readonly AICostGuard $guard,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function ask(Job $job, array $history, string $question): string
    {
        if (! $this->isAvailable()) {
            return self::DISABLED_MESSAGE;
        }

        // Per-actor daily cap (keyed by user or IP): over it, answer honestly
        // that the limit is reached rather than spending another call.
        if (! $this->guard->allow('ask')) {
            return self::RATE_LIMITED_MESSAGE;
        }

        $trimmedHistory = array_slice($history, -self::MAX_HISTORY_MESSAGES);

        try {
            $answer = $this->ai->chat(
                $this->prompt->prompt($job, $trimmedHistory, $question),
                $this->prompt->system(),
                'ask',
            );
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Ask-about-job call failed', [
                'job_id' => $job->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE_MESSAGE;
        }

        $this->guard->hit('ask');

        $answer = trim($answer);

        return $answer === '' ? self::FAILURE_MESSAGE : Str::limit($answer, 1000, '');
    }
}
