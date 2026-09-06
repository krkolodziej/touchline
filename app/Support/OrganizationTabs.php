<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OrganizationRole;
use App\Http\Resources\OrganizationResource;
use App\Models\League;
use App\Models\OrganizationMembership;
use App\Models\Player;
use App\Models\Team;
use App\Support\Scope\OrganizationScope;

/**
 * The chrome every organization tab shares: which organization, what you may do in it, and
 * how many rows sit behind each tab.
 *
 * Built here rather than in each controller so a new tab cannot come out with a different
 * header, and so the four counts are four queries rather than four per tab.
 */
class OrganizationTabs
{
    /** @return array<string, mixed> */
    public function props(OrganizationScope $scope): array
    {
        $organizationId = $scope->organization()->id;

        $counts = [
            'leagues' => League::query()->where('organization_id', $organizationId)->count(),
            'clubs' => Team::query()->where('organization_id', $organizationId)->count(),
            'players' => Player::query()->where('organization_id', $organizationId)->count(),
            'members' => OrganizationMembership::query()->where('organization_id', $organizationId)->count(),
        ];

        return [
            'organization' => (new OrganizationResource(
                $scope->organization(),
                $scope->role(),
                $counts['members'],
            ))->resolve(),
            'counts' => $counts,
            'can_manage' => $scope->role()->canManage(),
            'can_delete' => $scope->role() === OrganizationRole::Owner,
        ];
    }
}
