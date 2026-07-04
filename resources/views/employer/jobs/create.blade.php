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

                        {{--
                            Job-description generator. Shown in BOTH provider states: with AI on it
                            writes the draft; with AI off the service assembles a deterministic
                            template from the same structured inputs (no API call). Copy adapts so
                            the off-state never claims to be AI — AiDisabledDegradationTest asserts
                            "Generate description with AI" is absent when the AI layer is disabled.
                        --}}
                        @php($aiAssist = $aiAssistEnabled)
                        <div class="rounded-lg border border-brand-100 bg-brand-50/60 p-4"
                            x-data="{
                                show: false,
                                seniority: '',
                                skillsText: '',
                                genLocation: '',
                                salary: '',
                                generating: false,
                                failed: false,
                                justGenerated: false,
                                generate() {
                                    const title = document.getElementById('title').value.trim();
                                    if (! title) {
                                        this.failed = true;
                                        return;
                                    }
                                    const skills = this.skillsText.split(/[\n,]/).map((s) => s.trim()).filter(Boolean);
                                    this.generating = true;
                                    this.failed = false;
                                    fetch('{{ route('employer.jobs.draft-description') }}', {
                                        method: 'POST',
                                        headers: {
                                            Accept: 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        },
                                        body: JSON.stringify({
                                            title,
                                            seniority: this.seniority.trim(),
                                            skills,
                                            location: this.genLocation.trim(),
                                            salary: this.salary.trim(),
                                        }),
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
                                class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                <span x-show="!show">{{ $aiAssist ? 'Generate description with AI' : 'Draft a starter description' }}</span>
                                <span x-show="show" x-cloak>Hide description assistant</span>
                            </button>

                            <div x-show="show" x-cloak class="mt-3 space-y-3">
                                <p class="text-xs text-gray-600">
                                    @if ($aiAssist)
                                        Give us a few details and we'll draft a description and requirements list using the title above for you to edit.
                                    @else
                                        Give us a few details and we'll assemble a starter description and requirements list from the title above for you to edit.
                                    @endif
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <input type="text" x-model="seniority" placeholder="Seniority (e.g. Senior)"
                                        class="block w-full text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" />
                                    <input type="text" x-model="genLocation" placeholder="Location (e.g. Berlin / Remote)"
                                        class="block w-full text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" />
                                    <input type="text" x-model="salary" placeholder="Salary band (e.g. €60k–€80k)"
                                        class="block w-full text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" />
                                    <input type="text" x-model="skillsText" placeholder="Must-have skills (comma separated)"
                                        class="block w-full text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" />
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="generate()" :disabled="generating"
                                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span x-text="generating ? 'Working…' : 'Generate'"></span>
                                    </button>
                                    <template x-if="failed">
                                        <span class="text-xs text-red-600">Add a job title above, then try again.</span>
                                    </template>
                                </div>
                            </div>

                            <template x-if="justGenerated">
                                <p class="mt-2 text-xs text-brand-700">
                                    {{ $aiAssist ? 'AI-generated draft — review and edit before posting.' : 'Starter draft — review and edit before posting.' }}
                                </p>
                            </template>
                        </div>

                        <!-- Description -->
                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="6"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                                required minlength="100">{{ old('description') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">{{ __('At least 100 characters — describe what the role actually involves.') }}</p>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <!-- Requirements -->
                        <div>
                            <x-input-label for="requirements" :value="__('Requirements').' ('.__('optional').')'" />
                            <textarea id="requirements" name="requirements" rows="4"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">{{ old('requirements') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Leave blank, or write at least 20 characters.') }}</p>
                            <x-input-error :messages="$errors->get('requirements')" class="mt-2" />
                        </div>

                        {{--
                            Bias check on the drafted text. Always available — the service
                            flags with a model when AI is on and a curated keyword scan when
                            off — so no provider-state gating here.
                        --}}
                        <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-4"
                            x-data="{
                                checked: false,
                                checking: false,
                                failed: false,
                                flags: [],
                                check() {
                                    const parts = [
                                        document.getElementById('title').value,
                                        document.getElementById('description').value,
                                        document.getElementById('requirements').value,
                                    ];
                                    const text = parts.map((p) => p.trim()).filter(Boolean).join('\n\n');
                                    if (! text) {
                                        this.failed = true;
                                        return;
                                    }
                                    this.checking = true;
                                    this.failed = false;
                                    fetch('{{ route('employer.jobs.check-bias') }}', {
                                        method: 'POST',
                                        headers: {
                                            Accept: 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        },
                                        body: JSON.stringify({ text }),
                                    })
                                        .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                                        .then((data) => {
                                            if (data.status === 'ok' && data.result) {
                                                this.flags = data.result.flags;
                                                this.checked = true;
                                            } else {
                                                this.failed = true;
                                            }
                                        })
                                        .catch(() => { this.failed = true; })
                                        .finally(() => { this.checking = false; });
                                },
                            }">
                            <div class="flex items-center gap-3">
                                <button type="button" @click="check()" :disabled="checking"
                                    class="text-sm font-medium text-amber-800 hover:text-amber-900 disabled:opacity-50">
                                    <span x-text="checking ? 'Checking…' : 'Check for biased language'"></span>
                                </button>
                                <template x-if="failed">
                                    <span class="text-xs text-red-600">Add some description text, then try again.</span>
                                </template>
                            </div>

                            {{-- Clean pass --}}
                            <template x-if="checked && flags.length === 0">
                                <p class="mt-2 text-xs text-green-700">No exclusionary or gendered phrasing detected.</p>
                            </template>

                            {{-- Flags --}}
                            <template x-if="checked && flags.length">
                                <ul class="mt-3 space-y-2">
                                    <template x-for="(flag, i) in flags" :key="i">
                                        <li class="text-sm">
                                            <span class="font-semibold text-amber-900" x-text="'“' + flag.phrase + '”'"></span>
                                            <span class="text-gray-600" x-text="flag.issue"></span>
                                            <template x-if="flag.suggestion">
                                                <span class="block text-gray-500">Try: <span class="italic" x-text="flag.suggestion"></span></span>
                                            </template>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                        </div>

                        <!-- Type -->
                        <div>
                            <x-input-label for="type" :value="__('Job Type')" />
                            <select id="type" name="type"
                                class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
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
                                <x-input-label for="location" :value="__('Location').' ('.__('required unless remote').')'" />
                                <x-text-input id="location" name="location" type="text" class="mt-1 block w-full"
                                    :value="old('location')" placeholder="City, Country" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_remote" value="1"
                                        {{ old('is_remote') ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
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
