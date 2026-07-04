<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Prompts\BiasCheckPrompt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Flags exclusionary / gendered / discriminatory phrasing in job-description
 * text and suggests neutral rewrites.
 *
 * Always useful, in every provider state. With AI enabled the flags come from
 * one LLM call constrained to strict JSON; with AI_PROVIDER=none (or on any
 * model failure) it degrades to a deterministic scan over a curated keyword
 * map — so employers always get a check, never a dead button.
 */
class BiasCheckService
{
    /**
     * Curated phrasing → {issue, suggestion} used by the deterministic
     * fallback. Matched case-insensitively as whole words. Deliberately
     * conservative: obvious, well-established coded terms only, so the
     * offline scan stays high-signal.
     *
     * @var array<string, array{issue: string, suggestion: string}>
     */
    private const KEYWORD_MAP = [
        'rockstar' => ['issue' => 'Hype term that skews masculine and can deter applicants.', 'suggestion' => 'skilled professional'],
        'ninja' => ['issue' => 'Hype term that skews masculine and can deter applicants.', 'suggestion' => 'expert'],
        'guru' => ['issue' => 'Hype term that can read as exclusionary.', 'suggestion' => 'specialist'],
        'superstar' => ['issue' => 'Hype term that can deter strong but modest applicants.', 'suggestion' => 'high performer'],
        'guys' => ['issue' => 'Gendered term for a mixed group.', 'suggestion' => 'team / everyone'],
        'he' => ['issue' => 'Gendered pronoun assumes the candidate is male.', 'suggestion' => 'they'],
        'she' => ['issue' => 'Gendered pronoun assumes the candidate is female.', 'suggestion' => 'they'],
        'salesman' => ['issue' => 'Gendered job title.', 'suggestion' => 'salesperson'],
        'manpower' => ['issue' => 'Gendered term.', 'suggestion' => 'workforce / staffing'],
        'young' => ['issue' => 'Age-coded language that may exclude older candidates.', 'suggestion' => 'motivated'],
        'energetic' => ['issue' => 'Age-coded language that can signal a bias toward younger candidates.', 'suggestion' => 'proactive'],
        'digital native' => ['issue' => 'Age-coded phrase that can exclude older candidates.', 'suggestion' => 'comfortable with digital tools'],
        'aggressive' => ['issue' => 'Aggression-coded trait that skews masculine.', 'suggestion' => 'driven'],
        'dominant' => ['issue' => 'Aggression-coded trait that skews masculine.', 'suggestion' => 'confident'],
        'culture fit' => ['issue' => 'Vague "fit" language that can mask bias.', 'suggestion' => 'shared values / alignment with our ways of working'],
    ];

    public function __construct(
        private readonly AIProvider $ai,
        private readonly BiasCheckPrompt $prompt,
        private readonly AICostGuard $guard,
    ) {}

    /**
     * Always true: the keyword fallback guarantees a useful result even with
     * the AI layer off, so callers never need to hide the feature.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    public function check(string $text): BiasCheckResult
    {
        // Model flags only when AI is on AND the employer is within their
        // daily cap; otherwise the keyword scan (no API call).
        if ($this->ai->isEnabled() && $this->guard->allow('bias-check')) {
            $model = $this->modelCheck($text);

            if ($model !== null) {
                return $model;
            }
        }

        return $this->keywordScan($text);
    }

    /**
     * One LLM call, parsed defensively. Null on any failure so the caller
     * degrades to the keyword scan instead of retrying.
     */
    private function modelCheck(string $text): ?BiasCheckResult
    {
        try {
            $raw = $this->ai->chat($this->prompt->prompt($text), $this->prompt->system(), 'bias-check');
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Bias check call failed; using keyword scan', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $this->guard->hit('bias-check');

        $flags = $this->parseJson($raw);

        if ($flags === null) {
            Log::channel('ai')->warning('Bias check could not be parsed; using keyword scan', []);

            return null;
        }

        return BiasCheckResult::fromArray($flags, BiasCheckResult::SOURCE_MODEL);
    }

    /**
     * Deterministic scan over the curated keyword map — no API call. Each
     * distinct matched term is flagged once, with its neutral rewrite.
     */
    private function keywordScan(string $text): BiasCheckResult
    {
        $flags = [];

        foreach (self::KEYWORD_MAP as $term => $meta) {
            if (preg_match('/\b'.preg_quote($term, '/').'\b/i', $text)) {
                $flags[] = [
                    'phrase' => $term,
                    'issue' => $meta['issue'],
                    'suggestion' => $meta['suggestion'],
                ];
            }
        }

        return BiasCheckResult::fromArray($flags, BiasCheckResult::SOURCE_RULES);
    }

    /**
     * Extract the flags array from raw model output, tolerating surrounding
     * prose / markdown fences by slicing to the outermost braces. Returns
     * null when the object can't be read at all; an explicit empty "flags"
     * array (a clean pass) returns [].
     *
     * @return array<int, mixed>|null
     */
    private function parseJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        if (! is_array($decoded) || ! array_key_exists('flags', $decoded)) {
            return null;
        }

        return is_array($decoded['flags']) ? $decoded['flags'] : [];
    }
}
