<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Profile') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($profile->isAnalyzingResume())
                {{--
                    The parse runs off-request on the queue. This polls a tiny
                    status endpoint and redirects to the review screen itself
                    the moment the job finishes — no manual refresh needed.
                --}}
                <div class="mb-6"
                    x-data="{
                        analyzing: true,
                        poll() {
                            fetch('{{ route('candidate.profile.resume-status') }}', { headers: { Accept: 'application/json' } })
                                .then((r) => r.json())
                                .then((data) => {
                                    if (data.ready) {
                                        window.location = '{{ route('candidate.profile.resume-suggestions.show') }}';
                                    } else if (!data.analyzing) {
                                        this.analyzing = false;
                                    }
                                })
                                .catch(() => {});
                        },
                        init() {
                            this.timer = setInterval(() => this.poll(), 4000);
                        },
                    }"
                    x-init="init()">
                    <x-candidate.feed-prompt
                        icon="spinner"
                        title="Your resume is being analyzed"
                        description="We're reading your CV to suggest profile updates — this page will jump to the review screen automatically as soon as it's ready." />
                </div>
            @elseif ($profile->hasPendingSuggestion())
                <div class="mb-6 p-4 bg-brand-50 border border-brand-200 rounded-lg flex items-center justify-between gap-4">
                    <p class="text-sm text-brand-800">
                        We analyzed your resume and found suggestions to fill in your profile.
                        Nothing is applied until you review it.
                    </p>
                    <a href="{{ route('candidate.profile.resume-suggestions.show') }}"
                        class="shrink-0 inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-brand-600 text-white hover:bg-brand-700">
                        Review suggestions
                    </a>
                </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Career Profile</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        This information helps employers get to know you.
                    </p>
                </div>

                <div class="p-6">
                    <form method="POST" action="{{ route('candidate.profile.update') }}"
                        enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <!-- Headline -->
                        <div>
                            <x-input-label for="headline" :value="__('Professional Headline')" />
                            <x-text-input id="headline" name="headline" type="text"
                                class="mt-1 block w-full"
                                :value="old('headline', $profile->headline)"
                                placeholder="e.g. Senior Laravel Developer" />
                            <x-input-error :messages="$errors->get('headline')" class="mt-2" />
                        </div>

                        <!-- Bio -->
                        <div>
                            <x-input-label for="bio" :value="__('Bio')" />
                            <textarea id="bio" name="bio" rows="4"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                placeholder="Tell employers a bit about yourself...">{{ old('bio', $profile->bio) }}</textarea>
                            <x-input-error :messages="$errors->get('bio')" class="mt-2" />
                        </div>

                        <!-- Skills -->
                        <div>
                            <x-input-label for="skills" :value="__('Skills')" />
                            <textarea id="skills" name="skills" rows="2"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                placeholder="PHP, Laravel, Vue.js, MySQL...">{{ old('skills', $profile->skills) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Separate skills with commas.</p>
                            <x-input-error :messages="$errors->get('skills')" class="mt-2" />
                        </div>

                        <!-- Experience + Location -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="experience_years" :value="__('Years of Experience')" />
                                <x-text-input id="experience_years" name="experience_years" type="number"
                                    min="0" max="50" class="mt-1 block w-full"
                                    :value="old('experience_years', $profile->experience_years)" />
                                <x-input-error :messages="$errors->get('experience_years')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="location" :value="__('Location')" />
                                <x-text-input id="location" name="location" type="text"
                                    class="mt-1 block w-full"
                                    :value="old('location', $profile->location)"
                                    placeholder="City, Country" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Phone + LinkedIn -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="phone" :value="__('Phone')" />
                                <x-text-input id="phone" name="phone" type="text"
                                    class="mt-1 block w-full"
                                    :value="old('phone', $profile->phone)"
                                    placeholder="+1 555 000 0000" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="linkedin_url" :value="__('LinkedIn URL')" />
                                <x-text-input id="linkedin_url" name="linkedin_url" type="url"
                                    class="mt-1 block w-full"
                                    :value="old('linkedin_url', $profile->linkedin_url)"
                                    placeholder="https://linkedin.com/in/..." />
                                <x-input-error :messages="$errors->get('linkedin_url')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Resume -->
                        <div>
                            <x-input-label for="resume" :value="__('Resume / CV')" />
                            @if ($profile->resume_path)
                                <div class="mt-2 mb-3 flex items-center gap-3">
                                    <a href="{{ route('candidate.profile.resume') }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 text-sm text-brand-600 hover:underline">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        View current resume
                                    </a>
                                    <span class="text-xs text-gray-400">Upload a new file to replace it.</span>
                                </div>
                            @endif
                            <input id="resume" name="resume" type="file" accept=".pdf"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                            <p class="mt-1 text-xs text-gray-500">PDF only, max 5MB.</p>
                            <x-input-error :messages="$errors->get('resume')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <x-primary-button>{{ __('Save Profile') }}</x-primary-button>
                            <a href="{{ route('candidate.dashboard') }}"
                                class="text-sm text-gray-600 hover:text-gray-900 underline">
                                {{ __('Back to Dashboard') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
