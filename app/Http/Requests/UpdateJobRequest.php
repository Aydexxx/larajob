<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'salary_min'   => ['nullable', 'integer', 'min:0'],
            'salary_max'   => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'location'     => ['nullable', 'string', 'max:255'],
            'is_remote'    => ['nullable', 'boolean'],
            'type'         => ['required', 'in:full-time,part-time,contract,internship'],
            'expires_at'   => ['nullable', 'date'],
        ];
    }
}
