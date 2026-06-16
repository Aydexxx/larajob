<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JobController extends Controller
{
    private function employerCompany()
    {
        return Auth::user()->companies()->first();
    }

    public function index(): View|RedirectResponse
    {
        $company = $this->employerCompany();

        if (! $company) {
            return redirect()->route('employer.company.create')
                ->with('info', 'Please create a company profile before managing jobs.');
        }

        $jobs = $company->jobs()->latest()->get();

        return view('employer.jobs.index', compact('jobs', 'company'));
    }

    public function create(): View|RedirectResponse
    {
        $company = $this->employerCompany();

        if (! $company) {
            return redirect()->route('employer.company.create')
                ->with('info', 'Please create a company profile before posting jobs.');
        }

        return view('employer.jobs.create');
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        $company = $this->employerCompany();

        if (! $company) {
            return redirect()->route('employer.company.create');
        }

        $company->jobs()->create([
            ...$request->validated(),
            'slug'      => Str::slug($request->input('title')),
            'is_remote' => $request->boolean('is_remote'),
            'status'    => 'active',
        ]);

        return redirect()->route('employer.jobs.index')
            ->with('success', 'Job listing created successfully.');
    }

    public function edit(Job $job): View
    {
        $this->authorize('update', $job);

        return view('employer.jobs.edit', compact('job'));
    }

    public function update(UpdateJobRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->update([
            ...$request->validated(),
            'slug'      => Str::slug($request->input('title')),
            'is_remote' => $request->boolean('is_remote'),
        ]);

        return redirect()->route('employer.jobs.index')
            ->with('success', 'Job listing updated successfully.');
    }

    public function destroy(Job $job): RedirectResponse
    {
        $this->authorize('delete', $job);

        $job->delete();

        return redirect()->route('employer.jobs.index')
            ->with('success', 'Job listing deleted.');
    }

    public function toggleStatus(Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->update([
            'status' => $job->status === 'active' ? 'closed' : 'active',
        ]);

        return redirect()->back()
            ->with('success', 'Job status updated.');
    }
}
