<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MatchEventType;
use App\Models\Fixture;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchEvent>
 */
class MatchEventFactory extends Factory
{
    protected $model = MatchEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'fixture_id' => Fixture::factory(),
            'type' => MatchEventType::Goal,
            'minute' => fake()->numberBetween(1, 90),
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'related_player_id' => null,
        ];
    }
}
