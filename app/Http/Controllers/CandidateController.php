<?php

namespace App\Http\Controllers;

use App\Services\AI\SkillGapAdvisorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CandidateController extends Controller
{
    public function dashboard(SkillGapAdvisorService $skillGapAdvisor): View
    {
        $user = Auth::user();
        $profile = $user->candidateProfile;
        $applicationCount = $user->applications()->count();
        $recentApplications = $user->applications()->with('job.company')->latest()->take(5)->get();

        // Secondary to the For You feed: a short, rule-based "learn these
        // next" list. Returns [] (hidden entirely) when AI is off or there's
        // nothing meaningful to suggest yet — see SkillGapAdvisorService.
        $skillRecommendations = $skillGapAdvisor->recommendationsFor($profile);

        return view('candidate.dashboard', compact(
            'user', 'profile', 'applicationCount', 'recentApplications', 'skillRecommendations'
        ));
    }
}
