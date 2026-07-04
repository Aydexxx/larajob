<?php

use App\Http\Controllers\AdminCompanyController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AskAboutJobController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CandidateCoverLetterDraftController;
use App\Http\Controllers\CandidateMatchController;
use App\Http\Controllers\CandidateProfileController;
use App\Http\Controllers\CandidateResumeSuggestionController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployerApplicationController;
use App\Http\Controllers\EmployerController;
use App\Http\Controllers\EmployerJobApplicantsController;
use App\Http\Controllers\EmployerJobDescriptionDraftController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobBiasCheckController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicJobController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/jobs', [PublicJobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job:slug}', [PublicJobController::class, 'show'])->name('jobs.show');

// "Ask about this role" chat (async JSON; grounded in the listing + company
// only). Public and unauthenticated like the job page itself; degrades to a
// fixed message rather than 404ing when AI is disabled.
Route::post('/jobs/{job:slug}/ask', [AskAboutJobController::class, 'store'])
    ->middleware('throttle:ai-ask')
    ->name('jobs.ask');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Candidate routes
Route::middleware(['auth', 'role:candidate'])->prefix('candidate')->name('candidate.')->group(function () {
    Route::get('/dashboard', [CandidateController::class, 'dashboard'])->name('dashboard');

    // Candidate profile
    Route::get('/profile', [CandidateProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [CandidateProfileController::class, 'update'])->name('profile.update');

    // View own profile resume via a signed/streamed URL (never public).
    Route::get('/profile/resume', [CandidateProfileController::class, 'resume'])->name('profile.resume');

    // Polled while a resume analysis is in flight (async JSON).
    Route::get('/profile/resume-status', [CandidateProfileController::class, 'resumeStatus'])->name('profile.resume-status');

    // Review screen for CV-parsed profile suggestions: the candidate
    // confirms/edits each field before anything touches the profile.
    Route::get('/profile/resume-suggestions', [CandidateResumeSuggestionController::class, 'show'])->name('profile.resume-suggestions.show');
    Route::post('/profile/resume-suggestions', [CandidateResumeSuggestionController::class, 'store'])->name('profile.resume-suggestions.store');
    Route::delete('/profile/resume-suggestions', [CandidateResumeSuggestionController::class, 'destroy'])->name('profile.resume-suggestions.destroy');

    // Applications
    Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::resource('/applications', ApplicationController::class)->only(['store', 'index', 'show', 'destroy']);

    // View the resume submitted with one's own application (signed/streamed).
    Route::get('/applications/{application}/resume', [ApplicationController::class, 'resume'])->name('applications.resume');

    // AI cover-letter draft for the apply form (async JSON; only meaningful when AI is enabled)
    Route::post('/applications/draft-cover-letter', [CandidateCoverLetterDraftController::class, 'store'])
        ->middleware('throttle:ai-draft')
        ->name('applications.draft-cover-letter');

    // AI match score for a job (async JSON; only meaningful when AI is enabled)
    Route::get('/jobs/{job:slug}/match', [CandidateMatchController::class, 'show'])
        ->middleware('throttle:ai-explain')
        ->name('jobs.match');
});

// Employer routes
Route::middleware(['auth', 'role:employer'])->prefix('employer')->name('employer.')->group(function () {
    Route::get('/dashboard', [EmployerController::class, 'dashboard'])->name('dashboard');

    // Company management
    Route::get('/company/create', [CompanyController::class, 'create'])->name('company.create');
    Route::post('/company', [CompanyController::class, 'store'])->name('company.store');
    Route::get('/company/edit', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('/company', [CompanyController::class, 'update'])->name('company.update');

    // Job management
    Route::resource('/jobs', JobController::class)->except(['show']);
    Route::patch('/jobs/{job}/toggle-status', [JobController::class, 'toggleStatus'])->name('jobs.toggle-status');

    // Ranked applicants for a job (owner-only). The page ranks by the free
    // deterministic score; each row lazily fetches its one-line AI summary.
    Route::get('/jobs/{job}/applicants', [EmployerJobApplicantsController::class, 'index'])->name('jobs.applicants');
    Route::get('/jobs/{job}/applicants/{application}/summary', [EmployerJobApplicantsController::class, 'summary'])
        ->middleware('throttle:ai-explain')
        ->name('jobs.applicant-summary');

    // AI description draft for the job create form (async JSON; assembles a
    // template when AI is off, so it works in every provider state)
    Route::post('/jobs/draft-description', [EmployerJobDescriptionDraftController::class, 'store'])
        ->middleware('throttle:ai-draft')
        ->name('jobs.draft-description');

    // Bias check on job-description text (async JSON; model flags when AI is
    // on, keyword-based scan when off — always available)
    Route::post('/jobs/check-bias', [JobBiasCheckController::class, 'store'])
        ->middleware('throttle:ai-draft')
        ->name('jobs.check-bias');

    // Application management
    Route::get('/applications', [EmployerApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}/match', [EmployerApplicationController::class, 'match'])
        ->middleware('throttle:ai-explain')
        ->name('applications.match');
    Route::get('/applications/{application}', [EmployerApplicationController::class, 'show'])->name('applications.show');
    Route::patch('/applications/{application}/status', [EmployerApplicationController::class, 'updateStatus'])->name('applications.update-status');
    Route::get('/applications/{application}/resume', [EmployerApplicationController::class, 'downloadResume'])->name('applications.resume');
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // User management
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/toggle-suspend', [AdminUserController::class, 'toggleSuspend'])->name('users.toggle-suspend');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    // Company management
    Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [AdminCompanyController::class, 'show'])->name('companies.show');
    Route::patch('/companies/{company}/toggle-verify', [AdminCompanyController::class, 'toggleVerify'])->name('companies.toggle-verify');

    // Job management
    Route::get('/jobs', [AdminJobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{job}', [AdminJobController::class, 'show'])->name('jobs.show');
    Route::patch('/jobs/{job}/force-close', [AdminJobController::class, 'forceClose'])->name('jobs.force-close');
    Route::delete('/jobs/{job}', [AdminJobController::class, 'destroy'])->name('jobs.destroy');
});

require __DIR__.'/auth.php';
