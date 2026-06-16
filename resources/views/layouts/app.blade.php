<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Flag scripted browsers before paint so reveal/motion CSS can hide
             its initial state without ever hiding content from no-JS clients. --}}
        <script>document.documentElement.classList.add('js');</script>

        <title>{{ isset($title) ? $title . ' · ' : '' }}{{ config('app.name', 'LaraJob') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak]{display:none !important;}</style>
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-700">
        <div id="nav-progress" aria-hidden="true"></div>

        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[70] focus:top-3 focus:left-3 focus:px-4 focus:py-2 focus:rounded-lg focus:bg-brand-600 focus:text-white focus:text-sm focus:font-semibold">
            Skip to content
        </a>

        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            <!-- Flash toasts -->
            <x-ui.toast />

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white border-b border-gray-200">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main id="main-content" class="flex-1">
                {{ $slot }}
            </main>

            @include('layouts.footer')
        </div>
    </body>
</html>
