<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $companies = Company::query()
            ->with('user')
            ->withCount('jobs')
            ->search($request->input('search'))
            ->when($request->input('verified') === 'yes', fn ($q) => $q->verified())
            ->when($request->input('verified') === 'no', fn ($q) => $q->verified(false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.companies.index', compact('companies'));
    }

    public function show(Company $company): View
    {
        $company->load('user');
        $company->loadCount('jobs');
        $company->load(['jobs' => fn ($q) => $q->latest()->take(10)]);

        return view('admin.companies.show', compact('company'));
    }

    public function toggleVerify(Company $company): RedirectResponse
    {
        Gate::authorize('manage-platform');

        $company->update(['is_verified' => ! $company->is_verified]);

        return back()->with('success', $company->is_verified
            ? 'Company verified.'
            : 'Company verification removed.');
    }
}
