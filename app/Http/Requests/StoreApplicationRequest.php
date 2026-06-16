<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_id'       => [
                'required',
                'integer',
                'exists:job_listings,id',
                Rule::unique('applications')->where('user_id', $this->user()->id),
            ],
            'cover_letter' => ['required', 'string', 'min:50', 'max:5000'],
            'resume'       => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'job_id.unique' => 'You have already applied to this job.',
        ];
    }
}
