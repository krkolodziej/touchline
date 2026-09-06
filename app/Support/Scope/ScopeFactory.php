<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns route parameters into a proven scope, or into a 404.
 *
 * The most important line in this class is the one that is missing: there is no branch that
 * says "the organization exists but you are not in it". Somebody who is not a member gets
 * exactly the same answer as somebody who asked for an id that was never issued. Answering
 * 403 there would confirm the organization exists, and iterating over ids while telling the
 * two apart would map the whole application to a stranger.
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
}
