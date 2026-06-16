<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CandidateController extends Controller
{
    /**
     * Display the candidate dashboard.
     */
    public function dashboard(): View
    {
        return view('candidate.dashboard', ['user' => Auth::user()]);
    }

    /**
     * Display the candidate's job applications.
     */
    public function applications(): View
    {
        $applications = Auth::user()->applications()->with('job')->get();

        return view('candidate.applications', ['applications' => $applications]);
    }
}
