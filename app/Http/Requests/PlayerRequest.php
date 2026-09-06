<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlayerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:-120 years'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Enter a first name.',
            'last_name.required' => 'Enter a last name.',
            'date_of_birth.before' => 'A date of birth cannot be in the future.',
            'date_of_birth.after' => 'Check the year — that is over 120 years ago.',
        ];
    }
}
