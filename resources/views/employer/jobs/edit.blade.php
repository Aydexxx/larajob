<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Job Listing') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('employer.jobs.update', $job) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('Job Title')" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                                :value="old('title', $job->title)" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Description -->
                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="6"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                required>{{ old('description', $job->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <!-- Requirements -->
                        <div>
                            <x-input-label for="requirements" :value="__('Requirements')" />
                            <textarea id="requirements" name="requirements" rows="4"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('requirements', $job->requirements) }}</textarea>
                            <x-input-error :messages="$errors->get('requirements')" class="mt-2" />
                        </div>

                        <!-- Type -->
                        <div>
                            <x-input-label for="type" :value="__('Job Type')" />
                            <select id="type" name="type"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                required>
                                @foreach (['full-time' => 'Full Time', 'part-time' => 'Part Time', 'contract' => 'Contract', 'internship' => 'Internship'] as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ old('type', $job->type) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <!-- Location + Remote -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="location" :value="__('Location')" />
                                <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                                    :value="old('location', $job->location)" placeholder="City, Country" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_remote" value="1"
                                        {{ old('is_remote', $job->is_remote) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-700">{{ __('Remote position') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Salary range -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="salary_min" :value="__('Min Salary')" />
                                <x-text-input id="salary_min" name="salary_min" type="number" min="0"
                                    class="mt-1 block w-full" :value="old('salary_min', $job->salary_min)"
                                    placeholder="e.g. 40000" />
                                <x-input-error :messages="$errors->get('salary_min')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="salary_max" :value="__('Max Salary')" />
                                <x-text-input id="salary_max" name="salary_max" type="number" min="0"
                                    class="mt-1 block w-full" :value="old('salary_max', $job->salary_max)"
                                    placeholder="e.g. 70000" />
                                <x-input-error :messages="$errors->get('salary_max')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Expires at -->
                        <div>
                            <x-input-label for="expires_at" :value="__('Expires At')" />
                            <x-text-input id="expires_at" name="expires_at" type="date" class="mt-1 block w-full"
                                :value="old('expires_at', $job->expires_at?->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                            <a href="{{ route('employer.jobs.index') }}"
                                class="text-sm text-gray-600 hover:text-gray-900 underline">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
