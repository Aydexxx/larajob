<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-2xl font-extrabold text-gray-900">Welcome back</h1>
        <p class="mt-1 text-sm text-gray-500">Sign in to continue to your LaraJob account.</p>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <x-ui.alert variant="info" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-ui.input
            name="email"
            label="Email"
            type="email"
            :value="old('email')"
            required
            autofocus
            autocomplete="username"
            placeholder="you@example.com" />

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                        Forgot password?
                    </a>
                @endif
            </div>
            <x-ui.input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="••••••••" />
        </div>

        <label for="remember_me" class="flex items-center gap-2.5 cursor-pointer">
            <input id="remember_me" type="checkbox" name="remember"
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span class="text-sm text-gray-600">Remember me</span>
        </label>

        <x-ui.button type="submit" size="lg" class="w-full">Log in</x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-500">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">Sign up</a>
    </p>
</x-guest-layout>
