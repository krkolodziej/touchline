<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonTeam>
 */
class SeasonTeamFactory extends Factory
{
    protected $model = SeasonTeam::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'team_id' => Team::factory(),
        ];
    }
}
