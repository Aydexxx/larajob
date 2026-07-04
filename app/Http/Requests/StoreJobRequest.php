<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Minimum lengths keep listings looking like professional job posts
     * rather than one-line placeholders. `requirements` stays optional (not
     * every posting needs a separate list), but if given must have real
     * substance; `location` is required unless the role is remote.
     */
    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'min:4', 'max:255'],
            'description'  => ['required', 'string', 'min:100', 'max:10000'],
            'requirements' => ['nullable', 'string', 'min:20', 'max:5000'],
            'salary_min'   => ['nullable', 'integer', 'min:0'],
            'salary_max'   => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'location'     => ['nullable', 'string', 'max:255', 'required_unless:is_remote,1'],
            'is_remote'    => ['nullable', 'boolean'],
            'type'         => ['required', 'in:full-time,part-time,contract,internship'],
            'expires_at'   => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.min' => 'Description must be at least :min characters — give candidates enough detail to know what the role actually involves.',
            'requirements.min' => 'Requirements must be at least :min characters, or leave this blank entirely.',
            'location.required_unless' => 'Location is required unless this is a remote position.',
        ];
    }
}
