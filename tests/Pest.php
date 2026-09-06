<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Database\Factories\OrganizationMembershipFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * A person inside an organization, with a role.
 *
 * Nearly every test in this application starts here, because nearly every query in this
 * application starts from a membership row.
 */
function memberOf(Organization $organization, OrganizationRole $role = OrganizationRole::Member): User
{
    $user = UserFactory::new()->createOne();

    OrganizationMembershipFactory::new()->createOne([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}
