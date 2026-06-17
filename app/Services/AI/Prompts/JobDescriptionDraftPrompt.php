<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

/**
 * Builds the system/user prompt for the employer job-description draft
 * assistant. The model is asked for strict JSON so the caller can drop the
 * description and requirements into their respective form fields.
 */
final class JobDescriptionDraftPrompt
{
    public function system(): string
    {
        return implode(' ', [
            'You write polished, professional job postings for a job board.',
            'Respond with a SINGLE valid JSON object and nothing else — no prose, no markdown, no code fences.',
            'Shape: {"description": string, "requirements": string}.',
            'description is 2-4 short plain-text paragraphs covering the role and responsibilities (no markdown).',
            'requirements is plain text, one requirement per line, with no markdown bullets or numbering.',
            'Use ONLY the title and bullet points given — never invent a specific tech stack, salary, benefits, or company facts that were not provided.',
        ]);
    }

    /**
     * @param  array<int, string>  $bullets
     */
    public function prompt(string $title, array $bullets): string
    {
        return implode("\n", [
            "Job title: {$title}",
            '',
            'Bullet points provided by the employer:',
            ...array_map(fn (string $bullet): string => "- {$bullet}", $bullets),
        ]);
    }
}
