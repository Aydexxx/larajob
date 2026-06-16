<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $usersByRole = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $jobsByStatus = Job::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total_users'        => (int) $usersByRole->sum(),
            'admins'             => (int) ($usersByRole['admin'] ?? 0),
            'employers'          => (int) ($usersByRole['employer'] ?? 0),
            'candidates'         => (int) ($usersByRole['candidate'] ?? 0),
            'total_companies'    => Company::count(),
            'verified_companies' => Company::verified()->count(),
            'total_jobs'         => (int) $jobsByStatus->sum(),
            'active_jobs'        => (int) ($jobsByStatus['active'] ?? 0),
            'closed_jobs'        => (int) ($jobsByStatus['closed'] ?? 0),
            'draft_jobs'         => (int) ($jobsByStatus['draft'] ?? 0),
            'total_applications' => Application::count(),
        ];

        $recentJobs = Job::with('company')->latest()->take(10)->get();
        $recentUsers = User::latest()->take(10)->get();

        return view('admin.dashboard', compact('stats', 'recentJobs', 'recentUsers'));
    }
}
