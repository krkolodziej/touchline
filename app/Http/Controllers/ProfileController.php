<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Club\ClubProfile;
use App\Domain\Player\PlayerProfile;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\TeamResource;
use App\Models\Player;
use App\Models\Team;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A club and a player each get their own address, outside any season.
 *
 * Their identity outlives one season — that is the whole reason a club is not a row on a
 * league and a player is not a row on a club — so the page that shows a career cannot live
 * inside one edition of a competition.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ClubProfile $clubs,
        private readonly PlayerProfile $players,
    ) {}

    public function club(OrganizationScope $organization, int $teamId): Response
    {
        Gate::authorize(Permission::VIEW, $organization);

        $team = Team::query()
            ->where('organization_id', $organization->organization()->id)
            ->find($teamId);

        if ($team === null) {
            throw new NotFoundHttpException;
        }

        $profile = $this->clubs->of($team);

        return Inertia::render('clubs/Show', [
            'organization' => [
                'id' => $organization->organization()->id,
                'name' => $organization->organization()->name,
            ],
            'club' => (new TeamResource(
                $team,
                count($profile['squad']),
                count($profile['seasons']),
            ))->resolve(),
            ...$profile,
        ]);
    }

    public function player(OrganizationScope $organization, int $playerId): Response
    {
        Gate::authorize(Permission::VIEW, $organization);

        $player = Player::query()
            ->where('organization_id', $organization->organization()->id)
            ->find($playerId);

        if ($player === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('players/Show', [
            'organization' => [
                'id' => $organization->organization()->id,
                'name' => $organization->organization()->name,
            ],
            'player' => (new PlayerResource($player))->resolve(),
            ...$this->players->of($player),
        ]);
    }
}
