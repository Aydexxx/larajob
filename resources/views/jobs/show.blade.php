<x-app-layout>
    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Back link -->
            <a href="{{ route('jobs.index') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
                ← Back to jobs
            </a>

            <div class="flex flex-col lg:flex-row gap-8">

                <!-- Main content -->
                <article class="flex-1 min-w-0">
                    <div class="bg-white border border-gray-200 rounded-xl p-7">

                        <!-- Header -->
                        <div class="flex items-start gap-4 mb-6">
                            @if ($job->company?->logo)
                                <img src="{{ Storage::url($job->company->logo) }}"
                                    alt="{{ $job->company->name }}"
                                    class="h-16 w-16 rounded-xl object-cover border border-gray-100 shrink-0" />
                            @else
                                <div class="h-16 w-16 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                                    <span class="text-2xl font-bold text-indigo-600">
                                        {{ mb_strtoupper(mb_substr($job->company?->name ?? '?', 0, 1)) }}
                                    </span>
                                </div>
                            @endif
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 leading-tight">{{ $job->title }}</h1>
                                <p class="text-gray-500 mt-1">{{ $job->company?->name }}</p>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="flex flex-wrap gap-2 mb-6">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-50 text-indigo-700">
                                {{ str_replace('-', ' ', ucfirst($job->type)) }}
                            </span>
                            @if ($job->is_remote)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-50 text-green-700">
                                    Remote
                                </span>
                            @endif
                            @if ($job->location)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                                    {{ $job->location }}
                                </span>
                            @endif
                            @if ($job->salary_min || $job->salary_max)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-50 text-yellow-700">
                                    @if ($job->salary_min && $job->salary_max)
                                        ${{ number_format($job->salary_min) }} – ${{ number_format($job->salary_max) }}
                                    @elseif ($job->salary_min)
                                        From ${{ number_format($job->salary_min) }}
                                    @else
                                        Up to ${{ number_format($job->salary_max) }}
                                    @endif
                                </span>
                            @endif
                            @if ($job->expires_at)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-500">
                                    Closes {{ $job->expires_at->format('M j, Y') }}
                                </span>
                            @endif
                        </div>

                        <!-- Description -->
                        <div class="prose prose-sm max-w-none text-gray-700 mb-8">
                            <h2 class="text-base font-semibold text-gray-900 mb-3">About the Role</h2>
                            <div class="whitespace-pre-line">{{ $job->description }}</div>
                        </div>

                        @if ($job->requirements)
                            <div class="prose prose-sm max-w-none text-gray-700">
                                <h2 class="text-base font-semibold text-gray-900 mb-3">Requirements</h2>
                                <div class="whitespace-pre-line">{{ $job->requirements }}</div>
                            </div>
                        @endif

                    </div>
                </article>

                <!-- Sidebar -->
                <aside class="lg:w-72 shrink-0 space-y-5">

                    <!-- Apply card -->
                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Apply for this role</h3>

                        @auth
                            @if (Auth::user()->role === 'candidate')
                                @if ($hasApplied)
                                    <div class="w-full text-center px-5 py-3 bg-green-50 text-green-700 font-medium rounded-lg border border-green-200">
                                        ✓ Already Applied
                                    </div>
                                    <a href="{{ route('candidate.applications.index') }}"
                                        class="block w-full text-center px-5 py-2 text-sm text-indigo-600 hover:underline mt-1">
                                        View my applications
                                    </a>
                                @else
                                    <a href="{{ route('candidate.applications.create', ['job_id' => $job->id]) }}"
                                        class="block w-full text-center px-5 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                                        Apply Now
                                    </a>
                                @endif
                            @elseif (Auth::user()->role === 'employer')
                                {{-- Employers don't apply --}}
                            @endif
                        @else
                            <p class="text-sm text-gray-500 mb-4">
                                You need an account to apply for this job.
                            </p>
                            <a href="{{ route('register') }}"
                                class="block w-full text-center px-5 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 mb-2">
                                Create Account
                            </a>
                            <a href="{{ route('login') }}"
                                class="block w-full text-center px-5 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 text-sm">
                                Log in
                            </a>
                        @endauth

                        <p class="text-xs text-gray-400 mt-4 text-center">
                            Posted {{ $job->created_at->diffForHumans() }}
                        </p>
                    </div>

                    <!-- Company card -->
                    @if ($job->company)
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h3 class="font-semibold text-gray-900 mb-4">About the Company</h3>

                            <div class="flex items-center gap-3 mb-4">
                                @if ($job->company->logo)
                                    <img src="{{ Storage::url($job->company->logo) }}"
                                        alt="{{ $job->company->name }}"
                                        class="h-10 w-10 rounded-lg object-cover border border-gray-100" />
                                @else
                                    <div class="h-10 w-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                        <span class="font-bold text-indigo-600">
                                            {{ mb_strtoupper(mb_substr($job->company->name, 0, 1)) }}
                                        </span>
                                    </div>
                                @endif
                                <div>
                                    <p class="font-medium text-gray-900 text-sm">{{ $job->company->name }}</p>
                                    @if ($job->company->location)
                                        <p class="text-xs text-gray-500">{{ $job->company->location }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($job->company->description)
                                <p class="text-sm text-gray-600 mb-4 line-clamp-4">
                                    {{ $job->company->description }}
                                </p>
                            @endif

                            @if ($job->company->website)
                                <a href="{{ $job->company->website }}" target="_blank" rel="noopener noreferrer"
                                    class="text-sm text-indigo-600 hover:underline">
                                    Visit website →
                                </a>
                            @endif
                        </div>
                    @endif

                </aside>

            </div>
        </div>
    </div>
</x-app-layout>
