<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Fixture;
use App\Models\MatchEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MatchEvent
 */
class MatchEventResource extends JsonResource
{
    public function __construct(MatchEvent $resource, private readonly Fixture $fixture)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fixture_id' => $this->fixture_id,
            'type' => $this->type->value,
            'minute' => $this->minute,
            'team_id' => $this->team_id,
            // Which side of the timeline it belongs on. Derived here rather than worked out
            // by the client, which would need the fixture to do it.
            'home' => $this->team_id === $this->fixture->home_team_id,
            'player_id' => $this->player_id,
            'player_name' => $this->player->full_name,
            'related_player_id' => $this->related_player_id,
            'related_player_name' => $this->relatedPlayer?->full_name,
        ];
    }
}
