<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminJobController extends Controller
{
    public function index(Request $request): View
    {
        $jobs = Job::query()
            ->with('company')
            ->withCount('applications')
            ->search($request->input('search'))
            ->status($request->input('status'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.jobs.index', compact('jobs'));
    }

    public function show(Job $job): View
    {
        $job->load('company');
        $job->loadCount('applications');

        return view('admin.jobs.show', compact('job'));
    }

    public function forceClose(Job $job): RedirectResponse
    {
        Gate::authorize('manage-platform');

        $job->update(['status' => 'closed']);

        return back()->with('success', 'Job force-closed.');
    }

    public function destroy(Job $job): RedirectResponse
    {
        Gate::authorize('manage-platform');

        $job->delete();

        return redirect()->route('admin.jobs.index')->with('success', 'Job deleted.');
    }
}
