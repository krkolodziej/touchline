<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Resources\SeasonResource;
use App\Models\SeasonTeam;
use App\Support\Scope\SeasonScope;

/**
 * The chrome every season screen shares: which season, the breadcrumb out to the league and
 * the organization that own it, and what the reader may do here.
 *
 * The breadcrumb is not decoration. A season is the deepest thing anybody links to, and
 * arriving at one from an email with no way back out is how an application feels like a
 * dead end.
 */
class SeasonTabs
{
    /** @return array<string, mixed> */
    public function props(SeasonScope $scope): array
    {
        $clubCount = SeasonTeam::query()
            ->where('season_id', $scope->season()->id)
            ->count();

        return [
            'organization' => [
                'id' => $scope->organization()->id,
                'name' => $scope->organization()->name,
            ],
            'league' => [
                'id' => $scope->league()->id,
                'name' => $scope->league()->name,
            ],
            'season' => (new SeasonResource($scope->season(), $clubCount))->resolve(),
            'counts' => ['clubs' => $clubCount],
            'can_manage' => $scope->role()->canManage(),
        ];
    }
}
