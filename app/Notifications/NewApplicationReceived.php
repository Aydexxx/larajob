<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Application $application,
        private readonly Job $job,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $candidate = $this->application->user;

        return (new MailMessage)
            ->subject("New Application for \"{$this->job->title}\"")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have received a new application for your job posting **{$this->job->title}**.")
            ->line("**Applicant:** {$candidate->name}")
            ->line("**Applied on:** {$this->application->created_at->format('F j, Y')}")
            ->action('Review Application', route('employer.applications.show', $this->application))
            ->line('Log in to your employer dashboard to review the application and update its status.');
    }
}
