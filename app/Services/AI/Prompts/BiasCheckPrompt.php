<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

/**
 * Builds the system/user prompt for the job-description bias check. The model
 * is asked for strict JSON so the caller can render each flagged phrase with
 * its issue and a suggested rewrite.
 */
final class BiasCheckPrompt
{
    public function system(): string
    {
        return implode(' ', [
            'You review job-posting text for exclusionary, gendered, or discriminatory language that could deter qualified candidates.',
            'Consider gender-coded words, age bias, ableist phrasing, culture-fit dog whistles, and unnecessary "rockstar/ninja"-style hype.',
            'Respond with a SINGLE valid JSON object and nothing else — no prose, no markdown, no code fences.',
            'Shape: {"flags": [{"phrase": string, "issue": string, "suggestion": string}]}.',
            'phrase is the exact wording from the text that is problematic.',
            'issue is a short explanation of why it may exclude candidates.',
            'suggestion is a neutral rewrite of that phrase.',
            'Only flag wording that actually appears in the text — never invent problems. If the text is clean, return {"flags": []}.',
        ]);
    }

    public function prompt(string $text): string
    {
        return "Review this job posting text:\n\n{$text}";
    }
}
