@extends('admin.layout')

@section('title', 'Companies')

@section('content')
    <!-- Filters -->
    <form method="GET" action="{{ route('admin.companies.index') }}"
        class="bg-white border border-gray-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Search by name or location"
            class="flex-1 border-gray-300 rounded-lg text-sm focus:ring-brand-500 focus:border-brand-500" />

        <select name="verified"
            class="sm:max-w-[180px] border-gray-300 rounded-lg text-sm focus:ring-brand-500 focus:border-brand-500">
            <option value="">All companies</option>
            <option value="yes" {{ request('verified') === 'yes' ? 'selected' : '' }}>Verified</option>
            <option value="no" {{ request('verified') === 'no' ? 'selected' : '' }}>Unverified</option>
        </select>

        <button type="submit"
            class="px-5 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 shrink-0">
            Filter
        </button>

        @if (request()->hasAny(['search', 'verified']))
            <a href="{{ route('admin.companies.index') }}"
                class="px-5 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 shrink-0 text-center">
                Clear
            </a>
        @endif
    </form>

    <x-data-table :headers="['Company', 'Owner', 'Jobs', 'Status', 'Actions']"
        :is-empty="$companies->isEmpty()" empty="No companies match your filters.">
        @foreach ($companies as $company)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-sm">
                    <a href="{{ route('admin.companies.show', $company) }}"
                        class="font-medium text-gray-900 hover:text-brand-600">{{ $company->name }}</a>
                    @if ($company->location)
                        <p class="text-xs text-gray-400">{{ $company->location }}</p>
                    @endif
                </td>
                <td class="px-5 py-3 text-sm text-gray-500">{{ $company->user?->name ?? '—' }}</td>
                <td class="px-5 py-3 text-sm text-gray-700">{{ $company->jobs_count }}</td>
                <td class="px-5 py-3">
                    @if ($company->is_verified)
                        <x-status-badge status="verified" />
                    @else
                        <x-status-badge status="unverified" />
                    @endif
                </td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.companies.show', $company) }}"
                            class="text-xs px-3 py-1.5 border border-brand-300 text-brand-600 rounded-md font-medium hover:bg-brand-50">
                            View
                        </a>
                        <form method="POST" action="{{ route('admin.companies.toggle-verify', $company) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="text-xs px-3 py-1.5 rounded-md border font-medium
                                {{ $company->is_verified
                                    ? 'border-gray-300 text-gray-600 hover:bg-gray-50'
                                    : 'border-green-300 text-green-600 hover:bg-green-50' }}">
                                {{ $company->is_verified ? 'Unverify' : 'Verify' }}
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <div class="mt-6">
        {{ $companies->links() }}
    </div>
@endsection
