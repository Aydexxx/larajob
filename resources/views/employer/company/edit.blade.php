<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Company Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('employer.company.update') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Company Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                :value="old('name', $company->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Description -->
                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="4"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $company->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <!-- Website -->
                        <div>
                            <x-input-label for="website" :value="__('Website')" />
                            <x-text-input id="website" name="website" type="url" class="mt-1 block w-full"
                                :value="old('website', $company->website)" placeholder="https://example.com" />
                            <x-input-error :messages="$errors->get('website')" class="mt-2" />
                        </div>

                        <!-- Location -->
                        <div>
                            <x-input-label for="location" :value="__('Location')" />
                            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                                :value="old('location', $company->location)" placeholder="City, Country" />
                            <x-input-error :messages="$errors->get('location')" class="mt-2" />
                        </div>

                        <!-- Logo -->
                        <div>
                            <x-input-label for="logo" :value="__('Logo')" />
                            @if ($company->logo)
                                <div class="mt-2 mb-3">
                                    <img src="{{ Storage::url($company->logo) }}" alt="{{ $company->name }}"
                                        class="h-16 w-16 rounded-full object-cover border border-gray-200" />
                                    <p class="mt-1 text-xs text-gray-500">{{ __('Current logo — upload a new file to replace it.') }}</p>
                                </div>
                            @endif
                            <input id="logo" name="logo" type="file" accept="image/*"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            <p class="mt-1 text-xs text-gray-500">{{ __('PNG, JPG, GIF up to 2MB') }}</p>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                            <a href="{{ route('employer.jobs.index') }}"
                                class="text-sm text-gray-600 hover:text-gray-900 underline">
                                {{ __('Back to Jobs') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
