<?php

namespace App\Http\Controllers;

use App\Models\CandidateProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CandidateProfileController extends Controller
{
    public function edit(): View
    {
        $profile = Auth::user()->candidateProfile ?? new CandidateProfile();

        return view('candidate.profile.edit', compact('profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'headline'         => ['nullable', 'string', 'max:255'],
            'bio'              => ['nullable', 'string', 'max:2000'],
            'skills'           => ['nullable', 'string', 'max:1000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'location'         => ['nullable', 'string', 'max:255'],
            'linkedin_url'     => ['nullable', 'url', 'max:255'],
            'resume'           => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $data = collect($validated)->except('resume')->toArray();

        if ($request->hasFile('resume')) {
            $existing = Auth::user()->candidateProfile?->resume_path;
            if ($existing) {
                Storage::disk('public')->delete($existing);
            }
            $data['resume_path'] = $request->file('resume')->store('resumes', 'public');
        }

        CandidateProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return redirect()->route('candidate.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }
}
