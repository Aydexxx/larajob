<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function update(User $user, Job $job): bool
    {
        return $user->companies()->where('id', $job->company_id)->exists();
    }

    public function delete(User $user, Job $job): bool
    {
        return $user->companies()->where('id', $job->company_id)->exists();
    }
}
