<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MatchEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatchEventRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(MatchEventType::values())],
            // 180 rather than 90: extra time, and stoppage time recorded honestly as 90+5
            // rather than squeezed back into 90.
            'minute' => ['required', 'integer', 'between:1,180'],
            'team_id' => ['required', 'integer', 'min:1'],
            'player_id' => ['required', 'integer', 'min:1'],
            'related_player_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.in' => 'Choose a goal, a card or a substitution.',
            'minute.required' => 'Say which minute.',
            'minute.between' => 'A minute is between :min and :max.',
            'team_id.required' => 'Choose the club.',
            'player_id.required' => 'Choose the player.',
        ];
    }

    public function type(): MatchEventType
    {
        return MatchEventType::from($this->string('type')->value());
    }
}
