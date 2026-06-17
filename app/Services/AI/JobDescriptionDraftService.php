<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Prompts\JobDescriptionDraftPrompt;
use RuntimeException;

/**
 * Generates an editable job-description + requirements draft from a title
 * and a few employer-supplied bullet points. The result always lands back
 * in the form fields for the employer to review and edit — it is never
 * saved directly.
 */
class JobDescriptionDraftService
{
    public function __construct(
        private readonly AIProvider $ai,
        private readonly JobDescriptionDraftPrompt $prompt,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    /**
     * @param  array<int, string>  $bullets
     * @return array{description: string, requirements: string}
     */
    public function draft(string $title, array $bullets): array
    {
        $raw = $this->ai->chat(
            $this->prompt->prompt($title, $bullets),
            $this->prompt->system(),
        );

        $parsed = $this->parseJson($raw);

        if ($parsed === null) {
            throw new RuntimeException('AI job description draft could not be parsed.');
        }

        return $parsed;
    }

    /**
     * Extract and validate a strict-JSON object from raw model output.
     * Tolerates surrounding prose / markdown fences by slicing to the
     * outermost braces. Returns null if the result isn't usable.
     *
     * @return array{description: string, requirements: string}|null
     */
    private function parseJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            return null;
        }

        $description = isset($decoded['description']) && is_string($decoded['description'])
            ? trim($decoded['description'])
            : '';

        if ($description === '') {
            return null;
        }

        $requirements = isset($decoded['requirements']) && is_string($decoded['requirements'])
            ? trim($decoded['requirements'])
            : '';

        return [
            'description' => $description,
            'requirements' => $requirements,
        ];
    }
}
