<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\SeasonTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterEntry>
 */
class RosterEntryFactory extends Factory
{
    protected $model = RosterEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'season_team_id' => SeasonTeam::factory(),
            'player_id' => Player::factory(),
            'shirt_number' => null,
            'position' => PlayerPosition::Midfielder,
            'captain' => false,
        ];
    }
}
