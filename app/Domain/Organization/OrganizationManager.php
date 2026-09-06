<?php

declare(strict_types=1);

namespace App\Domain\Organization;

use App\Enums\OrganizationRole;
use App\Exceptions\ConflictException;
use App\Exceptions\OwnerMembershipIsProtectedException;
use App\Models\Organization;
use App\Models\OrganizationMembership;
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

    public function delete(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            // Later stages hang leagues, clubs, players and everything under a season off
            // this row. Each adds its own step here, deepest first, because some of those
            // foreign keys are RESTRICT on purpose and cascading order is not ours to pick.
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
