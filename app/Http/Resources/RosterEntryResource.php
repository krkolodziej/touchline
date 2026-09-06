<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RosterEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RosterEntry
 */
class RosterEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'season_team_id' => $this->season_team_id,
            'player_id' => $this->player_id,
            'player_name' => $this->player->full_name,
            'shirt_number' => $this->shirt_number,
            'position' => $this->position?->value,
            'captain' => $this->captain,
        ];
    }
}
