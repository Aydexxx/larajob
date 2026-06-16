<x-guest-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h1 class="text-xl font-semibold mb-4">{{ __('Job Listings') }}</h1>

                    @forelse ($jobs as $job)
                        <p>
                            <a href="{{ route('jobs.show', $job) }}" class="underline">{{ $job->title }}</a>
                            — {{ $job->location }}
                        </p>
                    @empty
                        <p>{{ __('No job listings available at the moment.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
