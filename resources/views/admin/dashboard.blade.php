@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <!-- Primary stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat-card label="Total Users" :value="$stats['total_users']" color="indigo"
            :href="route('admin.users.index')" />
        <x-stat-card label="Companies" :value="$stats['total_companies']" color="purple"
            :sublabel="$stats['verified_companies'] . ' verified'"
            :href="route('admin.companies.index')" />
        <x-stat-card label="Jobs" :value="$stats['total_jobs']" color="blue"
            :sublabel="$stats['active_jobs'] . ' active'"
            :href="route('admin.jobs.index')" />
        <x-stat-card label="Applications" :value="$stats['total_applications']" color="green" />
    </div>

    <!-- Breakdown stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <x-stat-card label="Candidates" :value="$stats['candidates']" color="sky" />
        <x-stat-card label="Employers" :value="$stats['employers']" color="indigo" />
        <x-stat-card label="Admins" :value="$stats['admins']" color="purple" />
        <x-stat-card label="Draft / Closed Jobs"
            :value="$stats['draft_jobs'] . ' / ' . $stats['closed_jobs']" color="gray" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Recent jobs -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Latest Jobs</h2>
                <a href="{{ route('admin.jobs.index') }}" class="text-sm text-indigo-600 hover:underline">View all</a>
            </div>
            <x-data-table :headers="['Title', 'Company', 'Status']" :is-empty="$recentJobs->isEmpty()" empty="No jobs yet.">
                @foreach ($recentJobs as $job)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-sm">
                            <a href="{{ route('admin.jobs.show', $job) }}"
                                class="font-medium text-gray-900 hover:text-indigo-600">
                                {{ $job->title }}
                            </a>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-500">{{ $job->company?->name ?? '—' }}</td>
                        <td class="px-5 py-3"><x-status-badge :status="$job->status" /></td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>

        <!-- Recent registrations -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-gray-900">Latest Registrations</h2>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-indigo-600 hover:underline">View all</a>
            </div>
            <x-data-table :headers="['Name', 'Role', 'Joined']" :is-empty="$recentUsers->isEmpty()" empty="No users yet.">
                @foreach ($recentUsers as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-sm">
                            <a href="{{ route('admin.users.show', $user) }}"
                                class="font-medium text-gray-900 hover:text-indigo-600">
                                {{ $user->name }}
                            </a>
                            <p class="text-xs text-gray-400">{{ $user->email }}</p>
                        </td>
                        <td class="px-5 py-3"><x-status-badge :status="$user->role" /></td>
                        <td class="px-5 py-3 text-sm text-gray-500">{{ $user->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>

    </div>
@endsection
