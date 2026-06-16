<x-guest-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h1 class="text-xl font-semibold mb-2">{{ $job->title }}</h1>
                    <p class="mb-4">{{ $job->location }} — {{ $job->type }}</p>
                    <p>{{ $job->description }}</p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
