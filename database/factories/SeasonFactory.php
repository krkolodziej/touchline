<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\League;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2000, 2099);

        return [
            'league_id' => League::factory(),
            'name' => (string) $year,
            'start_date' => sprintf('%d-03-01', $year),
            'end_date' => sprintf('%d-11-30', $year),
        ];
    }
}
