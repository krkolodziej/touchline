<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Fixture
 */
class FixtureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'season_id' => $this->season_id,
            'round_number' => $this->round_number,
            'leg' => $this->leg,
            'home_team_id' => $this->home_team_id,
            'home_team_name' => $this->homeTeam->name,
            'home_team_short_name' => $this->homeTeam->display_short_name,
            'away_team_id' => $this->away_team_id,
            'away_team_name' => $this->awayTeam->name,
            'away_team_short_name' => $this->awayTeam->display_short_name,
            // A kick-off is an instant, and is formatted in the reader's own timezone: a
            // match at 15:00 in Rzeszów should read 14:00 in London, because that is when it
            // starts for them.
            'kick_off_at' => $this->kick_off_at?->toAtomString(),
            'status' => $this->status->value,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'started_at' => $this->started_at?->toAtomString(),
            'finished_at' => $this->finished_at?->toAtomString(),
            // What the server would accept right now, so a client disables a button instead
            // of offering one that will be refused. The client keeps no copy of the rules,
            // so the two cannot drift apart.
            'allowed_transitions' => $this->status->allowedTransitionValues(),
        ];
    }
}
