<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Job Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @forelse ($jobs as $job)
                        <p>{{ $job->title }} — {{ $job->status }}</p>
                    @empty
                        <p>{{ __('You have not posted any jobs yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
