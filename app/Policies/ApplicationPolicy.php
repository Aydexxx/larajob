<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    // --- Candidate abilities ---

    public function view(User $user, Application $application): bool
    {
        return $application->user_id === $user->id;
    }

    public function delete(User $user, Application $application): bool
    {
        return $application->user_id === $user->id;
    }

    // --- Employer abilities ---

    public function viewAsEmployer(User $user, Application $application): bool
    {
        return $user->companies()
            ->whereHas('jobs', fn ($q) => $q->where('id', $application->job_id))
            ->exists();
    }

    public function updateStatus(User $user, Application $application): bool
    {
        return $user->companies()
            ->whereHas('jobs', fn ($q) => $q->where('id', $application->job_id))
            ->exists();
    }
}
