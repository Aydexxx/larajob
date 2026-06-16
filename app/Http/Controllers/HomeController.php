<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Job;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featured = Job::active()->with('company')->latest()->take(6)->get();
        $totalJobs = Job::active()->count();
        $totalCompanies = Company::count();

        return view('home', compact('featured', 'totalJobs', 'totalCompanies'));
    }
}
