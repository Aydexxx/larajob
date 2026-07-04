<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Models\Job;

/**
 * Builds the system/user prompt for the per-job "Ask about this role" chat.
 * The model is instructed to answer strictly from the supplied listing
 * context and to say so plainly when something isn't covered — this
 * wording is the only thing standing between the feature and hallucinated
 * answers, so it is deliberately explicit and repeated.
 */
final class AskAboutJobPrompt
{
    public function system(): string
    {
        return implode(' ', [
            'You answer candidate questions about a single job listing for a job board.',
            'Answer ONLY using facts stated in the LISTING CONTEXT below — never use outside knowledge, assumptions, or general industry norms.',
            "If the answer is not stated in the listing, say plainly that the listing doesn't mention it and suggest the candidate apply or contact the employer to ask directly. Do not guess or infer.",
            'Keep answers short and conversational (1-4 sentences), with no markdown.',
        ]);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function prompt(Job $job, array $history, string $question): string
    {
        return implode("\n\n", array_filter([
            "== LISTING CONTEXT ==\n".$this->context($job),
            $history === [] ? null : "== CONVERSATION SO FAR ==\n".$this->transcript($history),
            "== CANDIDATE QUESTION ==\n{$question}",
        ]));
    }

    private function context(Job $job): string
    {
        $company = $job->company;

        return implode("\n", array_filter([
            "Title: {$job->title}",
            filled($job->location) ? "Location: {$job->location}" : null,
            $job->is_remote ? 'Remote: yes' : null,
            filled($job->type) ? "Type: {$job->type}" : null,
            ($job->salary_min || $job->salary_max) ? 'Salary: '.$this->salaryText($job) : null,
            $job->expires_at ? 'Closes: '.$job->expires_at->format('Y-m-d') : null,
            "Description: {$job->description}",
            filled($job->requirements) ? "Requirements: {$job->requirements}" : null,
            $company ? "Company: {$company->name}" : null,
            $company && filled($company->description) ? "About the company: {$company->description}" : null,
            $company && filled($company->location) ? "Company location: {$company->location}" : null,
            $company && filled($company->website) ? "Company website: {$company->website}" : null,
        ]));
    }

    private function salaryText(Job $job): string
    {
        return match (true) {
            (bool) $job->salary_min && (bool) $job->salary_max => "\${$job->salary_min} - \${$job->salary_max}",
            (bool) $job->salary_min => "from \${$job->salary_min}",
            default => "up to \${$job->salary_max}",
        };
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function transcript(array $history): string
    {
        return implode("\n", array_map(
            fn (array $turn): string => ($turn['role'] === 'assistant' ? 'Assistant' : 'Candidate').': '.$turn['content'],
            $history,
        ));
    }
}
