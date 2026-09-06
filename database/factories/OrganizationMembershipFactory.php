<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    protected $model = OrganizationMembership::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => OrganizationRole::Member,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['role' => OrganizationRole::Owner]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => ['role' => OrganizationRole::Admin]);
    }
}
