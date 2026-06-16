<nav x-data="{ open: false }" data-navbar class="sticky top-0 z-50 bg-white/85 backdrop-blur-md border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <x-brand-mark :href="route('home')" />
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('jobs.index')" :active="request()->routeIs('jobs.*')">
                        {{ __('Browse Jobs') }}
                    </x-nav-link>

                    @auth
                        @if (Auth::user()->role === 'employer')
                            <x-nav-link :href="route('employer.dashboard')" :active="request()->routeIs('employer.*')">
                                {{ __('Employer Dashboard') }}
                            </x-nav-link>
                        @elseif (Auth::user()->role === 'candidate')
                            <x-nav-link :href="route('candidate.dashboard')" :active="request()->routeIs('candidate.*')">
                                {{ __('Dashboard') }}
                            </x-nav-link>
                        @elseif (Auth::user()->role === 'admin')
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                                {{ __('Admin') }}
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Right side -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 pl-1.5 pr-2.5 py-1.5 rounded-full border border-gray-200 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-300 focus:outline-none transition">
                                <x-ui.avatar :name="Auth::user()->name" size="xs" :square="false" />
                                <span class="max-w-[8rem] truncate">{{ Auth::user()->name }}</span>
                                <svg class="fill-current h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-3 border-b border-gray-100">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <div class="flex items-center gap-2">
                        <x-ui.button :href="route('login')" variant="ghost" size="sm">
                            {{ __('Log in') }}
                        </x-ui.button>
                        <x-ui.button :href="route('register')" variant="primary" size="sm">
                            {{ __('Get started') }}
                        </x-ui.button>
                    </div>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 transition" aria-label="Toggle menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-gray-200 bg-white">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('jobs.index')" :active="request()->routeIs('jobs.*')">
                {{ __('Browse Jobs') }}
            </x-responsive-nav-link>

            @auth
                @if (Auth::user()->role === 'employer')
                    <x-responsive-nav-link :href="route('employer.dashboard')" :active="request()->routeIs('employer.*')">
                        {{ __('Employer Dashboard') }}
                    </x-responsive-nav-link>
                @elseif (Auth::user()->role === 'candidate')
                    <x-responsive-nav-link :href="route('candidate.dashboard')" :active="request()->routeIs('candidate.*')">
                        {{ __('Dashboard') }}
                    </x-responsive-nav-link>
                @elseif (Auth::user()->role === 'admin')
                    <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                        {{ __('Admin') }}
                    </x-responsive-nav-link>
                @endif
            @endauth
        </div>

        <!-- Responsive Settings Options -->
        @auth
            <div class="pt-4 pb-3 border-t border-gray-200">
                <div class="flex items-center gap-3 px-4">
                    <x-ui.avatar :name="Auth::user()->name" size="sm" :square="false" />
                    <div class="min-w-0">
                        <div class="font-semibold text-base text-gray-800 truncate">{{ Auth::user()->name }}</div>
                        <div class="font-medium text-sm text-gray-500 truncate">{{ Auth::user()->email }}</div>
                    </div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @else
            <div class="pt-4 pb-4 border-t border-gray-200 space-y-2 px-4">
                <x-ui.button :href="route('login')" variant="outline" size="md" class="w-full">
                    {{ __('Log in') }}
                </x-ui.button>
                <x-ui.button :href="route('register')" variant="primary" size="md" class="w-full">
                    {{ __('Get started') }}
                </x-ui.button>
            </div>
        @endauth
    </div>
</nav>
