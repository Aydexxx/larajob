<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

/**
 * Builds the system/user prompt for the employer job-description generator.
 * The model is asked for strict JSON so the caller can drop the description
 * and requirements into their respective form fields.
 *
 * Inputs are structured (title, seniority, must-have skills, location,
 * salary band) rather than free prose, so the same shape can also feed the
 * deterministic offline template when the AI layer is disabled.
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
            'Use ONLY the details given — never invent a specific tech stack, salary, benefits, or company facts that were not provided.',
        ]);
    }

    /**
     * @param  array{title: string, seniority?: ?string, skills?: array<int, string>, location?: ?string, salary?: ?string}  $inputs
     */
    public function prompt(array $inputs): string
    {
        $skills = $inputs['skills'] ?? [];

        return implode("\n", array_filter([
            "Job title: {$inputs['title']}",
            filled($inputs['seniority'] ?? null) ? "Seniority: {$inputs['seniority']}" : null,
            $skills !== [] ? 'Must-have skills: '.implode(', ', $skills) : null,
            filled($inputs['location'] ?? null) ? "Location: {$inputs['location']}" : null,
            filled($inputs['salary'] ?? null) ? "Salary band: {$inputs['salary']}" : null,
        ]));
    }
}
