<?php

declare(strict_types=1);

namespace App\Domain\Organization;

use App\Enums\OrganizationRole;
use App\Exceptions\ConflictException;
use App\Exceptions\OwnerMembershipIsProtectedException;
use App\Models\Fixture;
use App\Models\League;
use App\Models\MatchEvent;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use App\Support\SlugGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything that has to be true about organizations and their members, in one place that
 * knows nothing about HTTP.
 *
 * No Request, no Response, no session. The controller has already established *who* is
 * asking and *whether they may*; this class only knows what the rules are.
 */
class OrganizationManager
{
    public function __construct(private readonly SlugGenerator $slugs) {}

    /**
     * Creates the organization and the creator's ownership together.
     *
     * Both, or neither: an organization with no owner is a row nobody on earth has authority
     * over, so it can neither be administered nor deleted, and only a migration could clean
     * it up.
     */
    public function create(User $creator, string $name, ?string $requestedSlug): OrganizationMembership
    {
        return DB::transaction(function () use ($creator, $name, $requestedSlug): OrganizationMembership {
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => $this->resolveSlug($name, $requestedSlug),
                'created_by_id' => $creator->id,
            ]);

            return OrganizationMembership::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $creator->id,
                'role' => OrganizationRole::Owner,
            ]);
        });
    }

    public function rename(Organization $organization, string $name, ?string $requestedSlug): void
    {
        $organization->name = $name;

        if ($requestedSlug !== null && $requestedSlug !== $organization->slug) {
            $organization->slug = $this->resolveSlug($name, $requestedSlug);
        }

        $organization->save();
    }

    /**
     * Deleting an organization deletes everything inside it, **in an order chosen here**.
     *
     * `delete()` on its own is not enough, and the reason is a rule from another stage doing
     * its job. A match event points at the club and at the player with ON DELETE RESTRICT,
     * deliberately: deleting one player must not quietly erase his goals from the record.
     *
     * Cascading straight from the organization reaches clubs and players by two different
     * paths, and the database is free to take them in whichever order it likes — so it
     * removes a club while its goals still exist and refuses the whole delete. Which means a
     * competition that has actually been played becomes undeletable while one that has not
     * deletes perfectly well. A failure that only happens to real data is worse than one
     * that always happens.
     *
     * So the children go first, deepest first, in one transaction. The RESTRICT still guards
     * the case it was written for: one club, deleted on its own, is still refused.
     */
    public function delete(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $seasonIds = Season::query()
                ->whereIn('league_id', League::query()
                    ->select('id')
                    ->where('organization_id', $organization->id))
                ->pluck('id');

            if ($seasonIds->isNotEmpty()) {
                $fixtureIds = Fixture::query()->whereIn('season_id', $seasonIds)->pluck('id');
                $registrationIds = SeasonTeam::query()->whereIn('season_id', $seasonIds)->pluck('id');

                MatchEvent::query()->whereIn('fixture_id', $fixtureIds)->delete();
                Fixture::query()->whereIn('season_id', $seasonIds)->delete();
                RosterEntry::query()->whereIn('season_team_id', $registrationIds)->delete();
                SeasonTeam::query()->whereIn('season_id', $seasonIds)->delete();
                Season::query()->whereIn('id', $seasonIds)->delete();
            }

            foreach ([League::class, Player::class, Team::class] as $model) {
                $model::query()->where('organization_id', $organization->id)->delete();
            }

            // Memberships hang off the organization directly and cascade cleanly, so the row
            // itself can go last.
            $organization->delete();
        });
    }

    /**
     * Adds an existing account to the organization.
     *
     * There is no invitation flow, so the address has to belong to somebody already. That is
     * reported as a validation error on the `email` field rather than as a 404, because from
     * the caller's point of view it is the value they typed that is wrong.
     */
    public function addMember(Organization $organization, string $email, OrganizationRole $role): OrganizationMembership
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'No account uses this email address yet.',
            ]);
        }

        $exists = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw new ConflictException('That person is already a member.', 'already_a_member');
        }

        return OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    public function changeRole(OrganizationMembership $membership, OrganizationRole $role): void
    {
        $this->guardOwner($membership);

        $membership->role = $role;
        $membership->save();
    }

    public function removeMember(OrganizationMembership $membership): void
    {
        $this->guardOwner($membership);

        $membership->delete();
    }

    /**
     * Both write paths on a membership go through here, so the invariant is stated once.
     * Two separate checks in two controllers is how one of them ends up missing.
     */
    private function guardOwner(OrganizationMembership $membership): void
    {
        if ($membership->isOwner()) {
            throw new OwnerMembershipIsProtectedException;
        }
    }

    private function resolveSlug(string $name, ?string $requestedSlug): string
    {
        $source = $requestedSlug !== null && trim($requestedSlug) !== '' ? $requestedSlug : $name;

        return $this->slugs->uniqueSlug(
            $source,
            static fn (string $candidate): bool => Organization::query()->where('slug', $candidate)->exists(),
        );
    }
}
