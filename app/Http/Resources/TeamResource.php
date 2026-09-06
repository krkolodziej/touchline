<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    public function __construct(
        Team $resource,
        private readonly int $squadSize = 0,
        private readonly int $seasonsPlayed = 0,
    ) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'short_name' => $this->display_short_name,
            'slug' => $this->slug,
            'created_at' => $this->created_at->toAtomString(),
            'squad_size' => $this->squadSize,
            'seasons_played' => $this->seasonsPlayed,
        ];
    }
}
