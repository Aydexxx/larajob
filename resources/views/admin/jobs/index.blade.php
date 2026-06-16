@extends('admin.layout')

@section('title', 'Jobs')

@section('content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.jobs.index') }}"
        class="bg-white border border-gray-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Search by title or description"
            class="flex-1 border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />

        <select name="status"
            class="sm:max-w-[180px] border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All statuses</option>
            @foreach (['active' => 'Active', 'closed' => 'Closed', 'draft' => 'Draft'] as $value => $label)
                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit"
            class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shrink-0">
            Filter
        </button>

        @if (request()->hasAny(['search', 'status']))
            <a href="{{ route('admin.jobs.index') }}"
                class="px-5 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 shrink-0 text-center">
                Clear
            </a>
        @endif
    </form>

    <x-data-table :headers="['Title', 'Company', 'Applicants', 'Status', 'Actions']"
        :is-empty="$jobs->isEmpty()" empty="No jobs match your filters.">
        @foreach ($jobs as $job)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-sm">
                    <a href="{{ route('admin.jobs.show', $job) }}"
                        class="font-medium text-gray-900 hover:text-indigo-600">{{ $job->title }}</a>
                    <p class="text-xs text-gray-400">{{ str_replace('-', ' ', ucfirst($job->type)) }}</p>
                </td>
                <td class="px-5 py-3 text-sm text-gray-500">{{ $job->company?->name ?? '—' }}</td>
                <td class="px-5 py-3 text-sm text-gray-700">{{ $job->applications_count }}</td>
                <td class="px-5 py-3"><x-status-badge :status="$job->status" /></td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.jobs.show', $job) }}"
                            class="text-xs px-3 py-1.5 border border-indigo-300 text-indigo-600 rounded-md font-medium hover:bg-indigo-50">
                            View
                        </a>

                        @if ($job->status !== 'closed')
                            <form method="POST" action="{{ route('admin.jobs.force-close', $job) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                    class="text-xs px-3 py-1.5 border border-yellow-300 text-yellow-600 rounded-md font-medium hover:bg-yellow-50">
                                    Force Close
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.jobs.destroy', $job) }}"
                            onsubmit="return confirm('Permanently delete this job and its applications?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="text-xs px-3 py-1.5 border border-red-300 text-red-600 rounded-md font-medium hover:bg-red-50">
                                Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-6">
        {{ $jobs->links() }}
    </div>
@endsection
