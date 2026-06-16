<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Apply for a Job') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Job summary card -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6 flex items-center gap-4">
                @if ($job->company?->logo)
                    <img src="{{ Storage::url($job->company->logo) }}"
                        alt="{{ $job->company->name }}"
                        class="h-12 w-12 rounded-lg object-cover border border-gray-100 shrink-0" />
                @else
                    <div class="h-12 w-12 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                        <span class="font-bold text-indigo-600">
                            {{ mb_strtoupper(mb_substr($job->company?->name ?? '?', 0, 1)) }}
                        </span>
                    </div>
                @endif
                <div>
                    <h3 class="font-semibold text-gray-900">{{ $job->title }}</h3>
                    <p class="text-sm text-gray-500">
                        {{ $job->company?->name }}
                        @if ($job->location) · {{ $job->location }} @endif
                    </p>
                </div>
            </div>

            <!-- Application form -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Your Application</h3>
                </div>

                <div class="p-6">
                    <form method="POST" action="{{ route('candidate.applications.store') }}"
                        enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <input type="hidden" name="job_id" value="{{ $job->id }}" />

                        @error('job_id')
                            <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                                {{ $message }}
                            </div>
                        @enderror

                        <!-- Cover letter -->
                        <div>
                            <x-input-label for="cover_letter" :value="__('Cover Letter')" />
                            <p class="text-xs text-gray-500 mt-0.5 mb-1">
                                Min 50 characters. Tell the employer why you're a great fit.
                            </p>
                            <textarea id="cover_letter" name="cover_letter" rows="8"
                                class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                required minlength="50">{{ old('cover_letter') }}</textarea>
                            <x-input-error :messages="$errors->get('cover_letter')" class="mt-2" />
                        </div>

                        <!-- Resume -->
                        <div>
                            <x-input-label for="resume" :value="__('Resume (optional)')" />
                            @if (Auth::user()->candidateProfile?->resume_path)
                                <p class="text-xs text-gray-500 mt-0.5 mb-1">
                                    Your profile resume will be used if you don't upload one here.
                                </p>
                            @endif
                            <input id="resume" name="resume" type="file" accept=".pdf"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            <p class="mt-1 text-xs text-gray-500">PDF only, max 5MB.</p>
                            <x-input-error :messages="$errors->get('resume')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <x-primary-button>{{ __('Submit Application') }}</x-primary-button>
                            <a href="{{ route('jobs.show', $job) }}"
                                class="text-sm text-gray-600 hover:text-gray-900 underline">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
