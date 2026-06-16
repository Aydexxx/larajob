<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Employer Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Welcome + quick actions -->
            <div class="bg-white border border-gray-200 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-semibold text-gray-900 text-lg">Welcome back, {{ $user->name }}!</h3>
                    @if ($company)
                        <p class="text-sm text-gray-500 mt-1">{{ $company->name }}</p>
                    @else
                        <p class="text-sm text-amber-600 mt-1">You haven't created a company profile yet.</p>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if ($company)
                        <a href="{{ route('employer.jobs.create') }}"
                            class="inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 text-sm">
                            Post a Job
                        </a>
                    @else
                        <a href="{{ route('employer.company.create') }}"
                            class="inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 text-sm">
                            Create Company Profile
                        </a>
                    @endif
                </div>
            </div>

            @if ($company)

                <!-- Stats grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 text-center">
                        <p class="text-3xl font-bold text-gray-900">{{ $totalJobs }}</p>
                        <p class="text-sm text-gray-500 mt-1">Total Jobs</p>
                        <a href="{{ route('employer.jobs.index') }}"
                            class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                            Manage →
                        </a>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 text-center">
                        <p class="text-3xl font-bold text-green-600">{{ $activeJobs }}</p>
                        <p class="text-sm text-gray-500 mt-1">Active Jobs</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 text-center">
                        <p class="text-3xl font-bold text-indigo-600">{{ $totalApplications }}</p>
                        <p class="text-sm text-gray-500 mt-1">Total Applications</p>
                        <a href="{{ route('employer.applications.index') }}"
                            class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                            View all →
                        </a>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 text-center">
                        <p class="text-3xl font-bold text-yellow-500">{{ $pendingApplications }}</p>
                        <p class="text-sm text-gray-500 mt-1">Pending Review</p>
                        @if ($pendingApplications > 0)
                            <a href="{{ route('employer.applications.index', ['status' => 'pending']) }}"
                                class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                                Review →
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Recent applications -->
                @if ($recentApplications->isNotEmpty())
                    <div class="bg-white border border-gray-200 rounded-xl">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Recent Applications</h3>
                            <a href="{{ route('employer.applications.index') }}"
                                class="text-sm text-indigo-600 hover:underline">View all</a>
                        </div>

                        @php
                            $statusColors = [
                                'pending'  => 'bg-yellow-100 text-yellow-800',
                                'reviewed' => 'bg-blue-100 text-blue-800',
                                'accepted' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                            ];
                        @endphp

                        <div class="divide-y divide-gray-50">
                            @foreach ($recentApplications as $application)
                                <a href="{{ route('employer.applications.show', $application) }}"
                                    class="flex items-center justify-between px-6 py-3 hover:bg-gray-50">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $application->user->name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $application->job?->title }}
                                            · {{ $application->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $statusColors[$application->status] ?? 'bg-gray-100 text-gray-700' }} shrink-0">
                                        {{ ucfirst($application->status) }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            @endif

        </div>
    </div>
</x-app-layout>
