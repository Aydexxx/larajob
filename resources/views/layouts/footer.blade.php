<footer class="bg-gray-900 text-gray-400">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-10">
            <!-- Brand -->
            <div class="col-span-2 md:col-span-1">
                <x-brand-mark :href="route('home')" mono class="text-white" />
                <p class="mt-4 text-sm leading-relaxed text-gray-400 max-w-xs">
                    The global job board connecting ambitious talent with companies hiring worldwide.
                </p>
            </div>

            <!-- Candidates -->
            <div>
                <h3 class="text-sm font-semibold text-white">For Candidates</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="{{ route('jobs.index') }}" class="hover:text-white transition">Browse jobs</a></li>
                    <li><a href="{{ route('jobs.index', ['remote' => 1]) }}" class="hover:text-white transition">Remote jobs</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white transition">Create an account</a></li>
                </ul>
            </div>

            <!-- Employers -->
            <div>
                <h3 class="text-sm font-semibold text-white">For Employers</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="{{ route('register') }}" class="hover:text-white transition">Post a job</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white transition">Find candidates</a></li>
                    <li><a href="{{ route('login') }}" class="hover:text-white transition">Employer sign in</a></li>
                </ul>
            </div>

            <!-- Account -->
            <div>
                <h3 class="text-sm font-semibold text-white">Account</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="{{ route('login') }}" class="hover:text-white transition">Sign in</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white transition">Register</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-gray-500">&copy; {{ date('Y') }} LaraJob. All rights reserved.</p>
            <p class="text-2xs uppercase tracking-widest text-gray-600">Built with Laravel &amp; Tailwind CSS</p>
        </div>
    </div>
</footer>
