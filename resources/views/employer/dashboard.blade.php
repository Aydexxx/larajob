<x-app-layout>
    <x-slot name="title">Employer Dashboard</x-slot>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-900 leading-tight">Employer Dashboard</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Welcome + quick actions -->
            <x-ui.card class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-bold text-gray-900 text-lg">Welcome back, {{ $user->name }} 👋</h3>
                    @if ($company)
                        <p class="text-sm text-gray-500 mt-1">{{ $company->name }}</p>
                    @else
                        <p class="text-sm text-accent-600 mt-1">You haven't created a company profile yet.</p>
                    @endif
                </div>
                <div class="shrink-0">
                    @if ($company)
                        <x-ui.button :href="route('employer.jobs.create')">Post a job</x-ui.button>
                    @else
                        <x-ui.button :href="route('employer.company.create')">Create company profile</x-ui.button>
                    @endif
                </div>
            </x-ui.card>

            @if ($company)
                <!-- Stats grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="surface p-5 text-center">
                        <p class="text-3xl font-extrabold text-gray-900">{{ $totalJobs }}</p>
                        <p class="text-sm text-gray-500 mt-1">Total jobs</p>
                        <a href="{{ route('employer.jobs.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">Manage →</a>
                    </div>
                    <div class="surface p-5 text-center">
                        <p class="text-3xl font-extrabold text-green-600">{{ $activeJobs }}</p>
                        <p class="text-sm text-gray-500 mt-1">Active jobs</p>
                    </div>
                    <div class="surface p-5 text-center">
                        <p class="text-3xl font-extrabold text-brand-600">{{ $totalApplications }}</p>
                        <p class="text-sm text-gray-500 mt-1">Applications</p>
                        <a href="{{ route('employer.applications.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">View all →</a>
                    </div>
                    <div class="surface p-5 text-center">
                        <p class="text-3xl font-extrabold text-accent-500">{{ $pendingApplications }}</p>
                        <p class="text-sm text-gray-500 mt-1">Pending review</p>
                        @if ($pendingApplications > 0)
                            <a href="{{ route('employer.applications.index', ['status' => 'pending']) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">Review →</a>
                        @endif
                    </div>
                </div>

                <!-- Recent applications -->
                @if ($recentApplications->isNotEmpty())
                    <x-ui.card padding="p-0">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900">Recent applications</h3>
                            <a href="{{ route('employer.applications.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @foreach ($recentApplications as $application)
                                <a href="{{ route('employer.applications.show', $application) }}"
                                    class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-gray-50 transition">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <x-ui.avatar :name="$application->user->name" size="sm" :square="false" />
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $application->user->name }}</p>
                                            <p class="text-xs text-gray-500 truncate">
                                                {{ $application->job?->title }} · {{ $application->created_at->diffForHumans() }}
                                            </p>
                                        </div>
                                    </div>
                                    <x-ui.badge :status="$application->status" class="shrink-0" />
                                </a>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            @else
                <x-ui.card>
                    <x-ui.empty-state
                        title="Set up your company to start hiring"
                        description="Create a company profile, then post your first job to start receiving applications.">
                        <x-slot name="action">
                            <x-ui.button :href="route('employer.company.create')">Create company profile</x-ui.button>
                        </x-slot>
                    </x-ui.empty-state>
                </x-ui.card>
            @endif

        </div>
    </div>
</x-app-layout>
