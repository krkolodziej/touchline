<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Player
 */
class PlayerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            // A date, emitted as a date. An instant here would invent a midnight and a
            // timezone the value does not have.
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->age,
            'created_at' => $this->created_at->toAtomString(),
        ];
    }
}
