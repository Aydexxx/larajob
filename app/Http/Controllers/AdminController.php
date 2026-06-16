<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function dashboard(): View
    {
        return view('admin.dashboard', ['user' => Auth::user()]);
    }
}
