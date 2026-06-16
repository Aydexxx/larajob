@extends('admin.layout')

@section('title', 'Users')

@section('content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.users.index') }}"
        class="bg-white border border-gray-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Search by name or email"
            class="flex-1 border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />

        <select name="role"
            class="sm:max-w-[180px] border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All roles</option>
            @foreach (['admin' => 'Admin', 'employer' => 'Employer', 'candidate' => 'Candidate'] as $value => $label)
                <option value="{{ $value }}" {{ request('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit"
            class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shrink-0">
            Filter
        </button>

        @if (request()->hasAny(['search', 'role']))
            <a href="{{ route('admin.users.index') }}"
                class="px-5 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 shrink-0 text-center">
                Clear
            </a>
        @endif
    </form>

    <x-data-table :headers="['Name', 'Role', 'Status', 'Joined', 'Actions']"
        :is-empty="$users->isEmpty()" empty="No users match your filters.">
        @foreach ($users as $user)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-sm">
                    <a href="{{ route('admin.users.show', $user) }}"
                        class="font-medium text-gray-900 hover:text-indigo-600">{{ $user->name }}</a>
                    <p class="text-xs text-gray-400">{{ $user->email }}</p>
                </td>
                <td class="px-5 py-3"><x-status-badge :status="$user->role" /></td>
                <td class="px-5 py-3">
                    @if ($user->isSuspended())
                        <x-status-badge status="suspended" />
                    @else
                        <x-status-badge status="active-user">Active</x-status-badge>
                    @endif
                </td>
                <td class="px-5 py-3 text-sm text-gray-500">{{ $user->created_at->format('M j, Y') }}</td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.users.show', $user) }}"
                            class="text-xs px-3 py-1.5 border border-indigo-300 text-indigo-600 rounded-md font-medium hover:bg-indigo-50">
                            View
                        </a>

                        @if ($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.toggle-suspend', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                    class="text-xs px-3 py-1.5 rounded-md border font-medium
                                    {{ $user->isSuspended()
                                        ? 'border-green-300 text-green-600 hover:bg-green-50'
                                        : 'border-yellow-300 text-yellow-600 hover:bg-yellow-50' }}">
                                    {{ $user->isSuspended() ? 'Activate' : 'Suspend' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                onsubmit="return confirm('Permanently delete {{ addslashes($user->name) }} and all their data?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-xs px-3 py-1.5 border border-red-300 text-red-600 rounded-md font-medium hover:bg-red-50">
                                    Delete
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-gray-400">You</span>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
@endsection
