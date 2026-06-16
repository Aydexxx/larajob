<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featured = Job::active()->with('company')->latest()->take(6)->get();

        $companies = Company::query()
            ->withCount(['jobs as active_jobs_count' => fn ($q) => $q->active()])
            ->whereHas('jobs', fn ($q) => $q->active())
            ->orderByDesc('active_jobs_count')
            ->take(8)
            ->get();

        $totalJobs = Job::active()->count();
        $totalCompanies = Company::count();
        $totalCandidates = User::where('role', 'candidate')->count();

        return view('home', compact(
            'featured',
            'companies',
            'totalJobs',
            'totalCompanies',
            'totalCandidates',
        ));
    }
}
