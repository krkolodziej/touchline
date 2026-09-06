<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Organization\OrganizationManager;
use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Models\OrganizationMembership;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MembershipController extends Controller
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    public function store(AddMemberRequest $request, OrganizationScope $scope): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $this->organizations->addMember(
            $scope->organization(),
            $request->string('email')->value(),
            $request->role(),
        );

        return back();
    }

    public function update(
        UpdateMemberRoleRequest $request,
        OrganizationScope $scope,
        int $membershipId,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $scope);

        $this->organizations->changeRole($this->membership($scope, $membershipId), $request->role());

        return back();
    }

    public function destroy(OrganizationScope $scope, int $membershipId): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $this->organizations->removeMember($this->membership($scope, $membershipId));

        return back();
    }

    /**
     * A membership reached through the wrong organization is a 404, the same answer a
     * membership that never existed gets. The nesting in the URL is load-bearing, not
     * decorative.
     */
    private function membership(OrganizationScope $scope, int $membershipId): OrganizationMembership
    {
        $membership = OrganizationMembership::query()
            ->where('organization_id', $scope->organization()->id)
            ->find($membershipId);

        if ($membership === null) {
            throw new NotFoundHttpException;
        }

        return $membership;
    }
}
