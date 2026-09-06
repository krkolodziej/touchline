<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class GenerateFixturesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'double_round' => ['boolean'],
            'first_round_on' => ['nullable', 'date'],
            'days_between_rounds' => ['nullable', 'integer', 'between:1,60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'days_between_rounds.between' => 'Space the rounds between :min and :max days apart.',
        ];
    }

    public function doubleRound(): bool
    {
        // Double by default: an amateur league that plays everybody once is the exception.
        return ! $this->has('double_round') || $this->boolean('double_round');
    }

    public function firstRoundOn(): ?CarbonImmutable
    {
        $value = $this->date('first_round_on');

        return $value === null ? null : CarbonImmutable::parse($value);
    }

    public function daysBetweenRounds(): int
    {
        $value = $this->input('days_between_rounds');

        return is_numeric($value) ? (int) $value : 7;
    }
}
