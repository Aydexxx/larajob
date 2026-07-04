<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

/**
 * Builds the system/user prompt for the resume-parsing assistant. Kept as a
 * dedicated, version-controlled class — rather than an inline string in a
 * controller or service — so the wording is reviewable and testable on its
 * own.
 */
final class ResumeParsePrompt
{
    /** Resumes longer than this are truncated; the signal is at the top. */
    private const MAX_INPUT_CHARS = 15000;

    public function system(): string
    {
        return implode(' ', [
            'You extract structured profile data from resume text.',
            'Respond with a SINGLE valid JSON object and nothing else — no prose, no markdown, no code fences.',
            'Shape: {"headline": string|null, "bio": string|null, "skills": string[], "years_of_experience": integer|null, "location": string|null, "links": string[]}.',
            'headline is a one-line professional title (e.g. "Senior Backend Engineer"), max 120 characters.',
            'bio is a 2-4 sentence first-person summary of the candidate, max 800 characters.',
            'skills lists concrete technologies and competencies found in the resume (max 20 short items).',
            'years_of_experience is the total professional experience as a whole number, or null if unclear.',
            'location is "City, Country" if stated, otherwise null.',
            'links lists any URLs found (LinkedIn, GitHub, portfolio), max 5.',
            'Use ONLY facts present in the resume — never invent or embellish.',
            'Use null or [] for anything the resume does not contain.',
        ]);
    }

    public function prompt(string $resumeText): string
    {
        return implode("\n", [
            'Extract the profile fields from the following resume text.',
            '',
            '== RESUME ==',
            str(trim($resumeText))->limit(self::MAX_INPUT_CHARS, '')->toString(),
        ]);
    }
}
