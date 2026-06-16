<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin · {{ config('app.name', 'LaraJob') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important;}</style>
</head>
<body class="font-sans antialiased bg-gray-100">
<div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">

    @php
        $navItems = [
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'active' => 'admin.dashboard'],
            ['route' => 'admin.users.index', 'label' => 'Users', 'active' => 'admin.users.*'],
            ['route' => 'admin.companies.index', 'label' => 'Companies', 'active' => 'admin.companies.*'],
            ['route' => 'admin.jobs.index', 'label' => 'Jobs', 'active' => 'admin.jobs.*'],
        ];
    @endphp

    <!-- Mobile overlay -->
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
        class="fixed inset-0 bg-gray-900/50 z-30 lg:hidden"></div>

    <!-- Sidebar -->
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 text-gray-300 transform transition-transform duration-200 lg:translate-x-0 lg:static lg:inset-auto lg:z-auto shrink-0">

        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                <span class="text-white font-bold text-lg">{{ config('app.name', 'LaraJob') }}</span>
                <span class="px-2 py-0.5 rounded bg-purple-600 text-white text-xs font-semibold">Admin</span>
            </a>
        </div>

        <nav class="px-3 py-4 space-y-1">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}"
                    class="block px-3 py-2 rounded-lg text-sm font-medium transition
                    {{ request()->routeIs($item['active'])
                        ? 'bg-gray-800 text-white'
                        : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="absolute bottom-0 inset-x-0 p-4 border-t border-gray-800">
            <a href="{{ route('home') }}" class="block px-3 py-2 text-sm text-gray-400 hover:text-white">
                ← Back to site
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-gray-400 hover:text-white">
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 min-w-0 flex flex-col">

        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-lg font-semibold text-gray-800">@yield('title', 'Admin')</h1>
            </div>
            <div class="text-sm text-gray-500">
                {{ Auth::user()->name }}
            </div>
        </header>

        <!-- Content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
