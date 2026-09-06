<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTeamRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['team_id' => ['required', 'integer', 'min:1']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['team_id.required' => 'Choose a club.'];
    }
}
