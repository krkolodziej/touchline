<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\League;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<League>
 */
class LeagueFactory extends Factory
{
    protected $model = League::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->city().' League';

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => '',
        ];
    }
}
