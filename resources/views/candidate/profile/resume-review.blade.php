<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Review Resume Suggestions') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Suggestions from your resume</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Nothing is saved until you confirm. Untick anything you don't want,
                        and edit the suggested values freely before applying.
                    </p>
                </div>

                @if (empty($fields))
                    <div class="p-6">
                        <p class="text-sm text-gray-600">
                            We couldn't extract any profile information from your resume.
                            Your profile has not been changed — you can fill it in manually,
                            or try uploading a clearer PDF (scanned image-only files can't be read).
                        </p>
                        <form method="POST" action="{{ route('candidate.profile.resume-suggestions.destroy') }}" class="mt-6">
                            @csrf
                            @method('DELETE')
                            <div class="flex items-center gap-4">
                                <x-primary-button>{{ __('OK, back to my profile') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="p-6">
                        <form method="POST" action="{{ route('candidate.profile.resume-suggestions.store') }}" class="space-y-6">
                            @csrf

                            @foreach ($fields as $field => $row)
                                <div class="border border-gray-100 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <input id="apply_{{ $field }}" name="apply[]" type="checkbox"
                                            value="{{ $field }}" checked
                                            class="mt-1 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        <div class="flex-1">
                                            <label for="apply_{{ $field }}" class="block text-sm font-medium text-gray-700">
                                                {{ $row['label'] }}
                                            </label>

                                            @if ($field === 'bio')
                                                <textarea name="values[{{ $field }}]" rows="4"
                                                    class="mt-2 block w-full text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">{{ old("values.$field", $row['suggested']) }}</textarea>
                                            @elseif ($field === 'skills')
                                                <textarea name="values[{{ $field }}]" rows="2"
                                                    class="mt-2 block w-full text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">{{ old("values.$field", $row['suggested']) }}</textarea>
                                            @else
                                                <x-text-input name="values[{{ $field }}]"
                                                    type="{{ $field === 'years_of_experience' ? 'number' : 'text' }}"
                                                    class="mt-2 block w-full text-sm"
                                                    :value="old('values.'.$field, $row['suggested'])" />
                                            @endif
                                            <x-input-error :messages="$errors->get('values.'.$field)" class="mt-2" />

                                            <p class="mt-2 text-xs text-gray-500">
                                                @if (filled($row['current']))
                                                    Current value: <span class="text-gray-700">{{ Str::limit((string) $row['current'], 120) }}</span>
                                                    <span class="text-amber-600">— applying will replace it.</span>
                                                @else
                                                    Currently empty on your profile.
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex items-center gap-4 pt-2">
                                <x-primary-button>{{ __('Apply Selected to Profile') }}</x-primary-button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('candidate.profile.resume-suggestions.destroy') }}" class="mt-4">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">
                                {{ __('Dismiss all suggestions') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
