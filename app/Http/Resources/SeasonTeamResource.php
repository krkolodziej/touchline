<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SeasonTeam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeasonTeam
 */
class SeasonTeamResource extends JsonResource
{
    public function __construct(SeasonTeam $resource, private readonly int $squadSize = 0)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'season_id' => $this->season_id,
            'team_id' => $this->team_id,
            'team_name' => $this->team->name,
            'team_short_name' => $this->team->display_short_name,
            'squad_size' => $this->squadSize,
        ];
    }
}
