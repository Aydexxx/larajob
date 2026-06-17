<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Models\CandidateProfile;
use App\Models\Job;

/**
 * Builds the system/user prompt for the candidate cover-letter draft
 * assistant. Kept as a dedicated, version-controlled class — rather than an
 * inline string in a controller or service — so the wording is reviewable
 * and testable on its own.
 */
final class CoverLetterDraftPrompt
{
    public function system(): string
    {
        return implode(' ', [
            'You write first-draft cover letters for job applicants.',
            'Write in first person, in a professional but warm tone, across 3-4 short paragraphs.',
            'Use ONLY the facts given about the candidate and the job — never invent skills, employers, names, or credentials that were not provided.',
            'Skip the salutation and sign-off; focus on the body of the letter.',
            'Output plain text only — no markdown, no headers, no bullet points, no code fences.',
            'This is a starting draft the candidate will personalize and edit before submitting — keep it concise, roughly 150-250 words.',
        ]);
    }

    public function prompt(CandidateProfile $profile, Job $job): string
    {
        return implode("\n", [
            'Write a draft cover letter for this candidate applying to this job.',
            '',
            '== CANDIDATE ==',
            $this->profileText($profile),
            '',
            '== JOB ==',
            $this->jobText($job),
        ]);
    }

    private function profileText(CandidateProfile $profile): string
    {
        return implode("\n", array_filter([
            filled($profile->headline) ? "Headline: {$profile->headline}" : null,
            filled($profile->bio) ? "Bio: {$profile->bio}" : null,
            filled($profile->skills) ? "Skills: {$profile->skills}" : null,
            $profile->experience_years !== null ? "Years of experience: {$profile->experience_years}" : null,
            filled($profile->location) ? "Location: {$profile->location}" : null,
        ]));
    }

    private function jobText(Job $job): string
    {
        return implode("\n", array_filter([
            "Title: {$job->title}",
            filled($job->company?->name) ? "Company: {$job->company->name}" : null,
            $job->description,
            $job->requirements,
            filled($job->location) ? "Location: {$job->location}" : null,
        ]));
    }
}
