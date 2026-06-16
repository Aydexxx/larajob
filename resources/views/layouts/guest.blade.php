<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LaraJob') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-700 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            <!-- Brand panel (desktop) -->
            <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 p-12 text-white">
                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 28px 28px;"></div>

                <div class="relative">
                    <x-brand-mark :href="route('home')" mono class="text-white" />
                </div>

                <div class="relative max-w-md">
                    <h2 class="text-3xl font-extrabold leading-tight">
                        Where careers and companies meet.
                    </h2>
                    <p class="mt-4 text-brand-100 leading-relaxed">
                        Join thousands of candidates and employers using LaraJob to find the right fit — across roles, industries, and countries.
                    </p>

                    <div class="mt-10 flex items-center gap-8">
                        <div>
                            <p class="text-3xl font-extrabold">10k+</p>
                            <p class="text-sm text-brand-200">Open roles</p>
                        </div>
                        <div class="h-10 w-px bg-white/20"></div>
                        <div>
                            <p class="text-3xl font-extrabold">2k+</p>
                            <p class="text-sm text-brand-200">Companies</p>
                        </div>
                        <div class="h-10 w-px bg-white/20"></div>
                        <div>
                            <p class="text-3xl font-extrabold">40+</p>
                            <p class="text-sm text-brand-200">Countries</p>
                        </div>
                    </div>
                </div>

                <p class="relative text-sm text-brand-200">&copy; {{ date('Y') }} LaraJob</p>
            </div>

            <!-- Form panel -->
            <div class="flex flex-col justify-center px-6 py-12 sm:px-12 bg-gray-50 lg:bg-white">
                <div class="lg:hidden mb-8 flex justify-center">
                    <x-brand-mark :href="route('home')" />
                </div>

                <div class="w-full sm:max-w-md mx-auto">
                    <div class="surface p-6 sm:p-8 lg:border-0 lg:shadow-none lg:p-0">
                        {{ $slot }}
                    </div>

                    <p class="mt-8 text-center text-sm text-gray-500">
                        <a href="{{ route('home') }}" class="font-medium text-gray-600 hover:text-brand-600 transition">
                            &larr; Back to home
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
