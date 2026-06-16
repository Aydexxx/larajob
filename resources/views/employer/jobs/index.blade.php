<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My Job Listings') }}
            </h2>
            <a href="{{ route('employer.jobs.create') }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                {{ __('Post New Job') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('info'))
                <div class="p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-md text-sm">
                    {{ session('info') }}
                </div>
            @endif

            <!-- Company info bar -->
            <div class="bg-white shadow-sm sm:rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if ($company->logo)
                        <img src="{{ Storage::url($company->logo) }}" alt="{{ $company->name }}"
                            class="h-10 w-10 rounded-full object-cover border border-gray-200" />
                    @endif
                    <div>
                        <p class="font-medium text-gray-900">{{ $company->name }}</p>
                        @if ($company->location)
                            <p class="text-sm text-gray-500">{{ $company->location }}</p>
                        @endif
                    </div>
                </div>
                <a href="{{ route('employer.company.edit') }}"
                    class="text-sm text-indigo-600 hover:text-indigo-800 underline">
                    {{ __('Edit Company') }}
                </a>
            </div>

            <!-- Jobs table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @forelse ($jobs as $job)
                    <div class="flex items-center justify-between p-4 border-b border-gray-100 last:border-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <p class="font-medium text-gray-900 truncate">{{ $job->title }}</p>

                                <!-- Type badge -->
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ str_replace('-', ' ', ucfirst($job->type)) }}
                                </span>

                                <!-- Status badge -->
                                @if ($job->status === 'active')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                        {{ __('Closed') }}
                                    </span>
                                @endif

                                @if ($job->is_remote)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                        {{ __('Remote') }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ __('Posted') }} {{ $job->created_at->diffForHumans() }}
                                @if ($job->location)
                                    &middot; {{ $job->location }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2 ml-4 shrink-0">
                            <!-- Application count badge -->
                            @if ($job->applications_count > 0)
                                <a href="{{ route('employer.applications.index', ['job_id' => $job->id]) }}"
                                    class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-md bg-indigo-50 text-indigo-700 font-medium hover:bg-indigo-100">
                                    {{ $job->applications_count }}
                                    {{ Str::plural('applicant', $job->applications_count) }}
                                </a>
                            @else
                                <span class="text-xs text-gray-400 px-2.5 py-1.5">0 applicants</span>
                            @endif

                            <!-- Toggle status -->
                            <form method="POST" action="{{ route('employer.jobs.toggle-status', $job) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                    class="text-xs px-3 py-1.5 rounded-md border font-medium
                                    {{ $job->status === 'active'
                                        ? 'border-red-300 text-red-600 hover:bg-red-50'
                                        : 'border-green-300 text-green-600 hover:bg-green-50' }}">
                                    {{ $job->status === 'active' ? __('Close') : __('Reopen') }}
                                </button>
                            </form>

                            <!-- Edit -->
                            <a href="{{ route('employer.jobs.edit', $job) }}"
                                class="text-xs px-3 py-1.5 rounded-md border border-indigo-300 text-indigo-600 font-medium hover:bg-indigo-50">
                                {{ __('Edit') }}
                            </a>

                            <!-- Delete -->
                            <form method="POST" action="{{ route('employer.jobs.destroy', $job) }}"
                                onsubmit="return confirm('{{ __('Delete this job listing?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-xs px-3 py-1.5 rounded-md border border-gray-300 text-gray-600 font-medium hover:bg-gray-50">
                                    {{ __('Delete') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500">
                        <p class="text-sm">{{ __('No job listings yet.') }}</p>
                        <a href="{{ route('employer.jobs.create') }}"
                            class="mt-2 inline-block text-sm text-indigo-600 hover:underline">
                            {{ __('Post your first job') }}
                        </a>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
