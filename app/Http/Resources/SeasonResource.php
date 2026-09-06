<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Season
 */
class SeasonResource extends JsonResource
{
    public function __construct(Season $resource, private readonly int $clubCount = 0)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'league_id' => $this->league_id,
            'name' => $this->name,
            // Dates as dates. An instant would invent a midnight and a timezone the value
            // does not have, and a reader an hour west would see the day before.
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'created_at' => $this->created_at->toAtomString(),
            'club_count' => $this->clubCount,
        ];
    }
}
