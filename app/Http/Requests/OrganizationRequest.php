<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrganizationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],

            // Optional. A slug nobody typed is derived from the name, and a slug that is
            // already taken gets a numeric suffix rather than a rejection — two clubs may
            // legitimately be called the same thing.
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the organization a name.',
            'name.min' => 'Use at least :min characters.',
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens.',
        ];
    }
}
