<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Organization\OrganizationManager;
use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Resources\MembershipResource;
use App\Models\OrganizationMembership;
use App\Support\OrganizationTabs;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MembershipController extends Controller
{
    public function __construct(
        private readonly OrganizationManager $organizations,
        private readonly OrganizationTabs $tabs,
    ) {}

    public function index(OrganizationScope $scope): Response
    {
        Gate::authorize(Permission::VIEW, $scope);

        // Owners first, then administrators, then members — a roster is read for authority
        // before it is read for names.
        $memberships = OrganizationMembership::query()
            ->with('user')
            ->join('users', 'users.id', '=', 'organization_memberships.user_id')
            ->select('organization_memberships.*')
            ->where('organization_memberships.organization_id', $scope->organization()->id)
            ->orderByRaw("CASE organization_memberships.role WHEN 'OWNER' THEN 0 WHEN 'ADMIN' THEN 1 ELSE 2 END")
            ->orderBy('users.email')
            ->get();

        return Inertia::render('organizations/Members', [
            ...$this->tabs->props($scope),
            'members' => MembershipResource::collection($memberships)->resolve(),
        ]);
    }

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
