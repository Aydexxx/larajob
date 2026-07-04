<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Prompts\JobDescriptionDraftPrompt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates an editable job-description + requirements draft from structured
 * inputs (title, seniority, must-have skills, location, salary band). The
 * result always lands back in the form fields for the employer to review and
 * edit — it is never saved directly.
 *
 * Works with EVERY provider setting. With AI enabled the draft comes from a
 * single LLM call constrained to strict JSON; with AI_PROVIDER=none (or on
 * any model failure) it degrades to a deterministic template assembled purely
 * from the inputs — no API call — so the generator is always useful. The
 * returned `source` records which path produced the draft.
 */
class JobDescriptionDraftService
{
    public const SOURCE_MODEL = 'model';

    public const SOURCE_TEMPLATE = 'template';

    public function __construct(
        private readonly AIProvider $ai,
        private readonly JobDescriptionDraftPrompt $prompt,
        private readonly AICostGuard $guard,
    ) {}

    /**
     * Whether a real model will write the draft. The generator itself is
     * always usable (see the template fallback) — this only tells the UI
     * whether to advertise the draft as AI-written.
     */
    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    /**
     * @param  array{title: string, seniority?: ?string, skills?: array<int, string>, location?: ?string, salary?: ?string}  $inputs
     * @return array{description: string, requirements: string, source: string}
     */
    public function draft(array $inputs): array
    {
        // Model draft only when AI is on AND the employer is within their
        // daily cap; otherwise fall through to the deterministic template.
        if ($this->ai->isEnabled() && $this->guard->allow('job-description')) {
            $model = $this->modelDraft($inputs);

            if ($model !== null) {
                return $model + ['source' => self::SOURCE_MODEL];
            }
        }

        return $this->template($inputs) + ['source' => self::SOURCE_TEMPLATE];
    }

    /**
     * One LLM call, constrained to strict JSON and parsed defensively. Null
     * on any failure (exception or unparseable output) — the caller degrades
     * to the template instead of retrying.
     *
     * @param  array{title: string, seniority?: ?string, skills?: array<int, string>, location?: ?string, salary?: ?string}  $inputs
     * @return array{description: string, requirements: string}|null
     */
    private function modelDraft(array $inputs): ?array
    {
        try {
            $raw = $this->ai->chat($this->prompt->prompt($inputs), $this->prompt->system(), 'job-description');
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Job description draft call failed; using template', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $this->guard->hit('job-description');

        $parsed = $this->parseJson($raw);

        if ($parsed === null) {
            Log::channel('ai')->warning('Job description draft could not be parsed; using template', []);
        }

        return $parsed;
    }

    /**
     * Deterministic draft assembled purely from the structured inputs — no
     * API call. A decent, honest starting point the employer will edit:
     * never invents a stack, salary, or company facts beyond what was given.
     *
     * @param  array{title: string, seniority?: ?string, skills?: array<int, string>, location?: ?string, salary?: ?string}  $inputs
     * @return array{description: string, requirements: string}
     */
    private function template(array $inputs): array
    {
        $title = trim($inputs['title']);
        $seniority = filled($inputs['seniority'] ?? null) ? trim((string) $inputs['seniority']) : null;
        $skills = $inputs['skills'] ?? [];
        $location = filled($inputs['location'] ?? null) ? trim((string) $inputs['location']) : null;
        $salary = filled($inputs['salary'] ?? null) ? trim((string) $inputs['salary']) : null;

        $role = $seniority !== null ? "{$seniority} {$title}" : $title;

        $intro = "We are hiring a {$role}".($location !== null ? " based in {$location}" : '').'.';

        $skillsSentence = $skills !== []
            ? 'In this role you will apply your experience with '.$this->humanList($skills).' to help move our team forward.'
            : 'In this role you will take ownership of meaningful work and help move our team forward.';

        $closing = 'You will collaborate closely with the wider team, contribute to key decisions, and grow your impact over time.'
            .($salary !== null ? " The salary band for this position is {$salary}." : '');

        $description = implode("\n\n", [$intro, $skillsSentence, $closing]);

        $requirementLines = array_merge(
            $seniority !== null ? ["Experience appropriate for a {$seniority} level"] : [],
            array_map(fn (string $skill): string => 'Experience with '.trim($skill), $skills),
            ['Strong communication and collaboration skills'],
        );

        return [
            'description' => $description,
            'requirements' => implode("\n", $requirementLines),
        ];
    }

    /**
     * Join a list into readable prose: "A", "A and B", "A, B and C".
     *
     * @param  array<int, string>  $items
     */
    private function humanList(array $items): string
    {
        $items = array_values(array_filter(array_map('trim', $items), fn (string $i): bool => $i !== ''));

        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
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
            'description' => Str::limit($description, 4000, ''),
            'requirements' => Str::limit($requirements, 2000, ''),
        ];
    }
}
