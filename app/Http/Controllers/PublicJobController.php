<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PublicJobController extends Controller
{
    public function index(Request $request): View
    {
        $jobs = Job::active()
            ->with('company')
            ->search($request->input('search'))
            ->when(
                $request->filled('location'),
                fn ($q) => $q->where('location', 'like', '%' . $request->input('location') . '%')
            )
            ->when(
                $request->filled('company'),
                fn ($q) => $q->whereHas('company', fn ($c) => $c->where('name', 'like', '%' . $request->input('company') . '%'))
            )
            ->when(
                $request->filled('types'),
                fn ($q) => $q->filterType((array) $request->input('types'))
            )
            ->when($request->boolean('remote'), fn ($q) => $q->remote())
            ->when(
                $request->filled('salary_min'),
                fn ($q) => $q->where('salary_max', '>=', (int) $request->input('salary_min'))
            )
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('jobs.index', compact('jobs'));
    }

    public function show(Job $job): View
    {
        abort_unless($job->status === 'active', 404);

        $job->load('company');

        $hasApplied = Auth::check()
            && Auth::user()->role === 'candidate'
            && Application::where('job_id', $job->id)->where('user_id', Auth::id())->exists();

        return view('jobs.show', compact('job', 'hasApplied'));
    }
}
