<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-2xl font-extrabold text-gray-900">Create your account</h1>
        <p class="mt-1 text-sm text-gray-500">Join LaraJob to find your next role or your next hire.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-ui.input
            name="name"
            label="Full name"
            type="text"
            :value="old('name')"
            required
            autofocus
            autocomplete="name"
            placeholder="Jane Doe" />

        <x-ui.input
            name="email"
            label="Email"
            type="email"
            :value="old('email')"
            required
            autocomplete="username"
            placeholder="you@example.com" />

        <!-- Role -->
        <div>
            <label for="role" class="block text-sm font-medium text-gray-700 mb-1.5">I am a</label>
            <select id="role" name="role" required
                class="block w-full rounded-xl border-gray-300 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                <option value="candidate" @selected(old('role') === 'candidate')>Candidate — looking for a job</option>
                <option value="employer" @selected(old('role') === 'employer')>Employer — hiring talent</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-1.5" />
        </div>

        <x-ui.input
            name="password"
            label="Password"
            type="password"
            required
            autocomplete="new-password"
            placeholder="••••••••" />

        <x-ui.input
            name="password_confirmation"
            label="Confirm password"
            type="password"
            required
            autocomplete="new-password"
            placeholder="••••••••" />

        <x-ui.button type="submit" size="lg" class="w-full">Create account</x-ui.button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-500">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Log in</a>
    </p>
</x-guest-layout>
