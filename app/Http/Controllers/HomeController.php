<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the home page.
     */
    public function index(): View
    {
        return view('welcome');
    }

    /**
     * Display the list of active job listings.
     */
    public function jobs(): View
    {
        $jobs = Job::where('status', 'active')->latest()->get();

        return view('jobs.index', ['jobs' => $jobs]);
    }

    /**
     * Display a single job listing.
     */
    public function show(Job $job): View
    {
        return view('jobs.show', ['job' => $job]);
    }
}
