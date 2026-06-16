<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\Job;
use App\Models\User;
use App\Policies\ApplicationPolicy;
use App\Policies\JobPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);

        // Platform-wide administrative gate — the second layer of defense
        // behind the role:admin route middleware. Used to guard destructive
        // admin actions (suspend, delete, verify, force-close).
        Gate::define('manage-platform', fn (User $user) => $user->isAdmin());
    }
}
