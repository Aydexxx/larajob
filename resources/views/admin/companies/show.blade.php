@extends('admin.layout')

@section('title', 'Company Detail')

@section('content')
    <a href="{{ route('admin.companies.index') }}"
        class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
        ← Back to companies
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="flex items-start gap-4">
                    @if ($company->logo)
                        <img src="{{ Storage::url($company->logo) }}" alt="{{ $company->name }}"
                            class="h-16 w-16 rounded-xl object-cover border border-gray-100 shrink-0" />
                    @else
                        <div class="h-16 w-16 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                            <span class="text-2xl font-bold text-indigo-600">{{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}</span>
                        </div>
                    @endif
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-gray-900">{{ $company->name }}</h2>
                            @if ($company->is_verified)
                                <x-status-badge status="verified" />
                            @else
                                <x-status-badge status="unverified" />
                            @endif
                        </div>
                        @if ($company->location)
                            <p class="text-sm text-gray-500 mt-1">{{ $company->location }}</p>
                        @endif
                        @if ($company->website)
                            <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer"
                                class="text-sm text-indigo-600 hover:underline">{{ $company->website }}</a>
                        @endif
                    </div>
                </div>

                @if ($company->description)
                    <p class="text-sm text-gray-700 mt-4 whitespace-pre-line">{{ $company->description }}</p>
                @endif
            </div>

            <!-- Jobs -->
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Recent Jobs ({{ $company->jobs_count }})</h3>
                @if ($company->jobs->isEmpty())
                    <p class="text-sm text-gray-500">This company has not posted any jobs.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($company->jobs as $job)
                            <a href="{{ route('admin.jobs.show', $job) }}"
                                class="flex items-center justify-between p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                                <span class="text-sm font-medium text-gray-900">{{ $job->title }}</span>
                                <x-status-badge :status="$job->status" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Side -->
        <aside class="space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Owner</h3>
                @if ($company->user)
                    <a href="{{ route('admin.users.show', $company->user) }}" class="block hover:bg-gray-50 -m-2 p-2 rounded-lg">
                        <p class="text-sm font-medium text-gray-900">{{ $company->user->name }}</p>
                        <p class="text-xs text-gray-400">{{ $company->user->email }}</p>
                    </a>
                @else
                    <p class="text-sm text-gray-500">No owner on record.</p>
                @endif
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Verification</h3>
                <form method="POST" action="{{ route('admin.companies.toggle-verify', $company) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                        class="w-full py-2 rounded-lg border text-sm font-medium
                        {{ $company->is_verified
                            ? 'border-gray-300 text-gray-700 hover:bg-gray-50'
                            : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                        {{ $company->is_verified ? 'Remove Verification' : 'Verify Company' }}
                    </button>
                </form>
            </div>
        </aside>
    </div>
@endsection
