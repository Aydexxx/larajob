@extends('admin.layout')

@section('title', 'Job Detail')

@section('content')
    <a href="{{ route('admin.jobs.index') }}"
        class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
        ← Back to jobs
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $job->title }}</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $job->company?->name ?? '—' }}
                            @if ($job->location) · {{ $job->location }} @endif
                        </p>
                    </div>
                    <x-status-badge :status="$job->status" />
                </div>

                <div class="flex flex-wrap gap-2 mt-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                        {{ str_replace('-', ' ', ucfirst($job->type)) }}
                    </span>
                    @if ($job->is_remote)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">Remote</span>
                    @endif
                    @if ($job->salary_min || $job->salary_max)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700">
                            @if ($job->salary_min && $job->salary_max)
                                ${{ number_format($job->salary_min) }} – ${{ number_format($job->salary_max) }}
                            @elseif ($job->salary_min)
                                From ${{ number_format($job->salary_min) }}
                            @else
                                Up to ${{ number_format($job->salary_max) }}
                            @endif
                        </span>
                    @endif
                </div>

                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Description</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $job->description }}</p>
                </div>

                @if ($job->requirements)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Requirements</h3>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $job->requirements }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Side -->
        <aside class="space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Details</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Applicants</dt>
                        <dd class="font-medium text-gray-900">{{ $job->applications_count }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Posted</dt>
                        <dd class="text-gray-900">{{ $job->created_at->format('M j, Y') }}</dd>
                    </div>
                    @if ($job->expires_at)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Expires</dt>
                            <dd class="text-gray-900">{{ $job->expires_at->format('M j, Y') }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($job->company)
                    <a href="{{ route('admin.companies.show', $job->company) }}"
                        class="mt-4 inline-block text-sm text-indigo-600 hover:underline">
                        View company →
                    </a>
                @endif
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-3">
                <h3 class="font-semibold text-gray-900">Actions</h3>

                @if ($job->status !== 'closed')
                    <form method="POST" action="{{ route('admin.jobs.force-close', $job) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="w-full py-2 rounded-lg border border-yellow-300 text-yellow-700 text-sm font-medium hover:bg-yellow-50">
                            Force Close Job
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.jobs.destroy', $job) }}"
                    onsubmit="return confirm('Permanently delete this job and its applications?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="w-full py-2 rounded-lg border border-red-300 text-red-700 text-sm font-medium hover:bg-red-50">
                        Delete Job
                    </button>
                </form>
            </div>
        </aside>
    </div>
@endsection
