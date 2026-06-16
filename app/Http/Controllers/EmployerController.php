<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployerController extends Controller
{
    /**
     * Display the employer dashboard.
     */
    public function dashboard(): View
    {
        return view('employer.dashboard', ['user' => Auth::user()]);
    }

    /**
     * Display the job listings managed by the employer's companies.
     */
    public function jobs(): View
    {
        $jobs = Auth::user()->companies->flatMap(fn ($company) => $company->jobs);

        return view('employer.jobs', ['jobs' => $jobs]);
    }
}
