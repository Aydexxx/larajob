<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same content-quality bar as StoreJobRequest — see there for rationale.
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
            'expires_at'   => ['nullable', 'date'],
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
