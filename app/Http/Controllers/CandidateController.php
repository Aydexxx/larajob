<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function dashboard(): View
    {
        $user = Auth::user();
        $profile = $user->candidateProfile;
        $applicationCount = $user->applications()->count();
        $recentApplications = $user->applications()->with('job.company')->latest()->take(5)->get();

        return view('candidate.dashboard', compact('user', 'profile', 'applicationCount', 'recentApplications'));
    }
}
