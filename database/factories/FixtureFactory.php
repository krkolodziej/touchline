<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\Fixture;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
{
    protected $model = Fixture::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'round_number' => 1,
            'leg' => 1,
            'kick_off_at' => now()->addWeek()->setTime(15, 0),
            'status' => MatchStatus::Scheduled,
            'home_score' => 0,
            'away_score' => 0,
        ];
    }
}
