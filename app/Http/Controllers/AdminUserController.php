<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->search($request->input('search'))
            ->role($request->input('role'))
            ->withCount(['companies', 'applications'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $user->loadCount(['companies', 'applications']);
        $user->load(['companies', 'candidateProfile']);

        return view('admin.users.show', compact('user'));
    }

    public function toggleSuspend(User $user): RedirectResponse
    {
        Gate::authorize('manage-platform');

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $user->suspended_at = $user->isSuspended() ? null : now();
        $user->save();

        $message = $user->isSuspended()
            ? 'User suspended.'
            : 'User reactivated.';

        return back()->with('success', $message);
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('manage-platform');

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
