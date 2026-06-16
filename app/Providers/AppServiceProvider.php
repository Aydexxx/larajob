<?php

namespace App\Providers;

use App\Models\Job;
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
    }
}
