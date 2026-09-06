<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\Fixture;
use App\Models\League;
use App\Models\Organization;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns route parameters into a proven scope, or into a 404.
 *
 * The most important line in this class is the one that is missing: there is no branch that
 * says "this exists but you are not in it". Somebody who is not a member gets exactly the
 * same answer as somebody who asked for an id that was never issued. Answering 403 there
 * would confirm the row exists, and iterating over ids while telling the two apart would map
 * the whole application to a stranger.
 *
 * Each level takes the level above it, already proven, and only has to establish that the
 * child belongs to it. So a season reached through the wrong league is a 404 too: the
 * nesting in the URL is load-bearing, not decorative.
 */
class ScopeFactory
{
    public function organizationScope(User $user, int $organizationId): OrganizationScope
    {
        /** @var Organization|null $organization */
        $organization = Organization::query()
            ->select('organizations.*')
            ->addSelect('organization_memberships.role as membership_role')
            ->join('organization_memberships', 'organization_memberships.organization_id', '=', 'organizations.id')
            ->where('organization_memberships.user_id', $user->id)
            ->where('organizations.id', $organizationId)
            ->first();

        if ($organization === null) {
            throw new NotFoundHttpException;
        }

        /** @var string $role */
        $role = $organization->getAttribute('membership_role');

        return new OrganizationScope($organization, OrganizationRole::from($role));
    }

    public function leagueScope(OrganizationScope $parent, int $leagueId): LeagueScope
    {
        $league = League::query()
            ->where('organization_id', $parent->organization()->id)
            ->find($leagueId);

        if ($league === null) {
            throw new NotFoundHttpException;
        }

        return new LeagueScope($parent, $league);
    }

    public function seasonScope(LeagueScope $parent, int $seasonId): SeasonScope
    {
        $season = Season::query()
            ->where('league_id', $parent->league()->id)
            ->find($seasonId);

        if ($season === null) {
            throw new NotFoundHttpException;
        }

        $season->setRelation('league', $parent->league());

        return new SeasonScope($parent, $season);
    }

    public function fixtureScope(SeasonScope $parent, int $fixtureId): FixtureScope
    {
        $fixture = Fixture::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('season_id', $parent->season()->id)
            ->find($fixtureId);

        if ($fixture === null) {
            throw new NotFoundHttpException;
        }

        $fixture->setRelation('season', $parent->season());

        return new FixtureScope($parent, $fixture);
    }

    public function seasonTeamScope(SeasonScope $parent, int $seasonTeamId): SeasonTeamScope
    {
        $seasonTeam = SeasonTeam::query()
            ->with('team')
            ->where('season_id', $parent->season()->id)
            ->find($seasonTeamId);

        if ($seasonTeam === null) {
            throw new NotFoundHttpException;
        }

        $seasonTeam->setRelation('season', $parent->season());

        return new SeasonTeamScope($parent, $seasonTeam);
    }
}
