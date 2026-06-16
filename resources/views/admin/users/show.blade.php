@extends('admin.layout')

@section('title', 'User Detail')

@section('content')
    <a href="{{ route('admin.users.index') }}"
        class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
        ← Back to users
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Profile -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $user->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                        <div class="flex items-center gap-2 mt-3">
                            <x-status-badge :status="$user->role" />
                            @if ($user->isSuspended())
                                <x-status-badge status="suspended" />
                            @else
                                <x-status-badge status="active-user">Active</x-status-badge>
                            @endif
                        </div>
                    </div>
                    <div class="text-right text-xs text-gray-400">
                        <p>Joined {{ $user->created_at->format('M j, Y') }}</p>
                        @if ($user->isSuspended())
                            <p class="text-red-500 mt-1">Suspended {{ $user->suspended_at->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Candidate profile -->
            @if ($user->role === 'candidate' && $user->candidateProfile)
                @php($p = $user->candidateProfile)
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Candidate Profile</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        @if ($p->headline)
                            <div><dt class="text-gray-500">Headline</dt><dd class="text-gray-900">{{ $p->headline }}</dd></div>
                        @endif
                        @if ($p->location)
                            <div><dt class="text-gray-500">Location</dt><dd class="text-gray-900">{{ $p->location }}</dd></div>
                        @endif
                        @if ($p->experience_years !== null)
                            <div><dt class="text-gray-500">Experience</dt><dd class="text-gray-900">{{ $p->experience_years }} years</dd></div>
                        @endif
                        @if ($p->phone)
                            <div><dt class="text-gray-500">Phone</dt><dd class="text-gray-900">{{ $p->phone }}</dd></div>
                        @endif
                    </dl>
                    @if ($p->skills)
                        <div class="mt-4">
                            <dt class="text-gray-500 text-sm mb-1.5">Skills</dt>
                            <div class="flex flex-wrap gap-1">
                                @foreach (array_filter(array_map('trim', explode(',', $p->skills))) as $skill)
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Companies owned -->
            @if ($user->companies->isNotEmpty())
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Companies</h3>
                    <div class="space-y-2">
                        @foreach ($user->companies as $company)
                            <a href="{{ route('admin.companies.show', $company) }}"
                                class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                                <span class="text-sm font-medium text-gray-900">{{ $company->name }}</span>
                                @if ($company->is_verified)
                                    <x-status-badge status="verified" />
                                @else
                                    <x-status-badge status="unverified" />
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Side: stats + actions -->
        <aside class="space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Activity</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Companies</dt>
                        <dd class="font-medium text-gray-900">{{ $user->companies_count }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Applications</dt>
                        <dd class="font-medium text-gray-900">{{ $user->applications_count }}</dd>
                    </div>
                </dl>
            </div>

            @if ($user->id !== auth()->id())
                <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-3">
                    <h3 class="font-semibold text-gray-900">Actions</h3>

                    <form method="POST" action="{{ route('admin.users.toggle-suspend', $user) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="w-full py-2 rounded-lg border text-sm font-medium
                            {{ $user->isSuspended()
                                ? 'border-green-300 text-green-700 hover:bg-green-50'
                                : 'border-yellow-300 text-yellow-700 hover:bg-yellow-50' }}">
                            {{ $user->isSuspended() ? 'Reactivate Account' : 'Suspend Account' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                        onsubmit="return confirm('Permanently delete this user and all their data?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="w-full py-2 rounded-lg border border-red-300 text-red-700 text-sm font-medium hover:bg-red-50">
                            Delete Account
                        </button>
                    </form>
                </div>
            @else
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-sm text-gray-500">
                    This is your own account. Manage it from your profile settings.
                </div>
            @endif
        </aside>
    </div>
@endsection
