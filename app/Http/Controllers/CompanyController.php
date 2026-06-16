<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $company = Auth::user()->companies()->first();

        if ($company) {
            return redirect()->route('employer.company.edit');
        }

        return view('employer.company.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'website'     => ['nullable', 'url', 'max:255'],
            'location'    => ['nullable', 'string', 'max:255'],
            'logo'        => ['nullable', 'image', 'max:2048'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
        }

        Auth::user()->companies()->create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'website'     => $validated['website'] ?? null,
            'location'    => $validated['location'] ?? null,
            'logo'        => $logoPath,
        ]);

        return redirect()->route('employer.jobs.index')
            ->with('success', 'Company profile created. You can now post jobs.');
    }

    public function edit(): View|RedirectResponse
    {
        $company = Auth::user()->companies()->first();

        if (! $company) {
            return redirect()->route('employer.company.create');
        }

        return view('employer.company.edit', compact('company'));
    }

    public function update(Request $request): RedirectResponse
    {
        $company = Auth::user()->companies()->firstOrFail();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'website'     => ['nullable', 'url', 'max:255'],
            'location'    => ['nullable', 'string', 'max:255'],
            'logo'        => ['nullable', 'image', 'max:2048'],
        ]);

        $data = [
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'website'     => $validated['website'] ?? null,
            'location'    => $validated['location'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $company->update($data);

        return redirect()->route('employer.company.edit')
            ->with('success', 'Company profile updated successfully.');
    }
}
