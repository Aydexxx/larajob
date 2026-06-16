<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-900 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.card>
                <h3 class="font-bold text-gray-900 text-lg">Welcome back, {{ Auth::user()->name }} 👋</h3>
                <p class="text-sm text-gray-500 mt-1">You're signed in. Where would you like to go?</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.button :href="route('jobs.index')">Browse jobs</x-ui.button>
                    @if (Auth::user()->role === 'candidate')
                        <x-ui.button :href="route('candidate.dashboard')" variant="outline">Candidate dashboard</x-ui.button>
                    @elseif (Auth::user()->role === 'employer')
                        <x-ui.button :href="route('employer.dashboard')" variant="outline">Employer dashboard</x-ui.button>
                    @elseif (Auth::user()->role === 'admin')
                        <x-ui.button :href="route('admin.dashboard')" variant="outline">Admin panel</x-ui.button>
                    @endif
                    <x-ui.button :href="route('profile.edit')" variant="ghost">Edit profile</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
