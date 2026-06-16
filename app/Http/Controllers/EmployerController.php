<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployerController extends Controller
{
    public function dashboard(): View
    {
        $user = Auth::user();
        $company = $user->companies()->first();

        $totalJobs = 0;
        $activeJobs = 0;
        $totalApplications = 0;
        $pendingApplications = 0;
        $recentApplications = collect();

        if ($company) {
            $jobIds = $company->jobs()->pluck('id');
            $totalJobs = $company->jobs()->count();
            $activeJobs = $company->jobs()->where('status', 'active')->count();
            $totalApplications = Application::whereIn('job_id', $jobIds)->count();
            $pendingApplications = Application::whereIn('job_id', $jobIds)->where('status', 'pending')->count();
            $recentApplications = Application::whereIn('job_id', $jobIds)
                ->with(['job', 'user'])
                ->latest()
                ->take(5)
                ->get();
        }

        return view('employer.dashboard', compact(
            'user',
            'company',
            'totalJobs',
            'activeJobs',
            'totalApplications',
            'pendingApplications',
            'recentApplications'
        ));
    }
}
