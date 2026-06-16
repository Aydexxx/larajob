<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Application $application,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->application->job;
        $companyName = $job->company?->name ?? 'the employer';
        $status = ucfirst($this->application->status);

        $statusDetail = match ($this->application->status) {
            'reviewed' => 'Your application is currently being reviewed. The employer will be in touch if they wish to proceed.',
            'accepted' => 'Congratulations! Your application has been accepted. The employer may contact you shortly with next steps.',
            'rejected' => 'Thank you for your interest. Unfortunately, the employer has decided not to move forward with your application at this time.',
            default => "Your application status has been updated to {$status}.",
        };

        return (new MailMessage)
            ->subject("Application Update: \"{$job->title}\"")
            ->greeting("Hello {$notifiable->name},")
            ->line("The status of your application for **{$job->title}** at **{$companyName}** has been updated.")
            ->line("**New Status:** {$status}")
            ->line($statusDetail)
            ->action('View Your Application', route('candidate.applications.show', $this->application))
            ->line('Thank you for using LaraJob. We wish you the best in your job search.');
    }
}
