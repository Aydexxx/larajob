<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Post a New Job') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('employer.jobs.store') }}" class="space-y-6">
                        @csrf

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('Job Title')" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                                :value="old('title')" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        @if ($aiAssistEnabled)
                            <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-4"
                                x-data="{
                                    show: false,
                                    bulletsText: '',
                                    generating: false,
                                    failed: false,
                                    justGenerated: false,
                                    generate() {
                                        const title = document.getElementById('title').value.trim();
                                        const bullets = this.bulletsText.split('\n').map((b) => b.trim()).filter(Boolean);
                                        if (! title || bullets.length === 0) {
                                            this.failed = true;
                                            return;
                                        }
                                        this.generating = true;
                                        this.failed = false;
                                        fetch('{{ route('employer.jobs.draft-description') }}', {
                                            method: 'POST',
                                            headers: {
                                                Accept: 'application/json',
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            },
                                            body: JSON.stringify({ title, bullets }),
                                        })
                                            .then((r) => (r.ok || r.status === 502 ? r.json() : Promise.reject(r)))
                                            .then((data) => {
                                                if (data.status === 'ok' && data.draft) {
                                                    document.getElementById('description').value = data.draft.description;
                                                    document.getElementById('requirements').value = data.draft.requirements;
                                                    this.justGenerated = true;
                                                    this.show = false;
                                                } else {
                                                    this.failed = true;
                                                }
                                            })
                                            .catch(() => { this.failed = true; })
                                            .finally(() => { this.generating = false; });
                                    },
                                }">
                                <button type="button" @click="show = !show"
                                    class="text-sm font-medium text-indigo-700 hover:text-indigo-800">
                                    <span x-show="!show">Generate description with AI</span>
                                    <span x-show="show" x-cloak>Hide AI assistant</span>
                                </button>

                                <div x-show="show" x-cloak class="mt-3 space-y-3">
                                    <p class="text-xs text-gray-600">
                                        Add a few bullet points about the role (one per line) — we'll draft a description and requirements list using the title above for you to edit.
                                    </p>
                                    <textarea x-model="bulletsText" rows="4"
                                        placeholder="e.g.&#10;3+ years building Laravel APIs&#10;Comfortable owning a feature end to end&#10;Remote-friendly, async-first team"
                                        class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                                    <div class="flex items-center gap-3">
                                        <button type="button" @click="generate()" :disabled="generating"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                            <span x-text="generating ? 'Generating…' : 'Generate'"></span>
                                        </button>
                                        <template x-if="failed">
                                            <span class="text-xs text-red-600">Add a title and at least one bullet point, then try again.</span>
                                        </template>
                                    </div>
                                </div>

                                <template x-if="justGenerated">
                                    <p class="mt-2 text-xs text-indigo-700">AI-generated draft — review and edit before posting.</p>
                                </template>
                            </div>
                        @endif

                        <!-- Description -->
                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="6"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                required>{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <!-- Requirements -->
                        <div>
                            <x-input-label for="requirements" :value="__('Requirements')" />
                            <textarea id="requirements" name="requirements" rows="4"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('requirements') }}</textarea>
                            <x-input-error :messages="$errors->get('requirements')" class="mt-2" />
                        </div>

                        <!-- Type -->
                        <div>
                            <x-input-label for="type" :value="__('Job Type')" />
                            <select id="type" name="type"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                required>
                                <option value="">{{ __('Select type…') }}</option>
                                @foreach (['full-time' => 'Full Time', 'part-time' => 'Part Time', 'contract' => 'Contract', 'internship' => 'Internship'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>
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
                                    :value="old('location')" placeholder="City, Country" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_remote" value="1"
                                        {{ old('is_remote') ? 'checked' : '' }}
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
                                    class="mt-1 block w-full" :value="old('salary_min')" placeholder="e.g. 40000" />
                                <x-input-error :messages="$errors->get('salary_min')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="salary_max" :value="__('Max Salary')" />
                                <x-text-input id="salary_max" name="salary_max" type="number" min="0"
                                    class="mt-1 block w-full" :value="old('salary_max')" placeholder="e.g. 70000" />
                                <x-input-error :messages="$errors->get('salary_max')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Expires at -->
                        <div>
                            <x-input-label for="expires_at" :value="__('Expires At')" />
                            <x-text-input id="expires_at" name="expires_at" type="date" class="mt-1 block w-full"
                                :value="old('expires_at')" />
                            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Post Job') }}</x-primary-button>
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
