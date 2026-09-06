<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\SeasonName;
use Illuminate\Foundation\Http\FormRequest;

class SeasonRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:32', new SeasonName],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Name the season.',
            'start_date.required' => 'Say when the season starts.',
            'end_date.after_or_equal' => 'A season cannot end before it starts.',
        ];
    }
}
