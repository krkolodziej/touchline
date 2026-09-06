<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PlayerPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RosterEntryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'player_id' => ['required', 'integer', 'min:1'],

            // Both optional. A squad list is built over a season, and refusing a player
            // because nobody has decided his number yet would just mean he is not entered.
            'shirt_number' => ['nullable', 'integer', 'between:1,99'],
            'position' => ['nullable', 'string', Rule::in(PlayerPosition::values())],
            'captain' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'player_id.required' => 'Choose a player.',
            'shirt_number.between' => 'A shirt number is between :min and :max.',
            'position.in' => 'Choose one of: goalkeeper, defender, midfielder, forward.',
        ];
    }

    public function position(): ?PlayerPosition
    {
        $value = $this->input('position');

        return is_string($value) && $value !== '' ? PlayerPosition::from($value) : null;
    }

    public function shirtNumber(): ?int
    {
        $value = $this->input('shirt_number');

        return is_numeric($value) ? (int) $value : null;
    }
}
