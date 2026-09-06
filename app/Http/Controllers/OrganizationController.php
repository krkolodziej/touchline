<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Organization\OrganizationManager;
use App\Http\Requests\OrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationManager $organizations) {}

    /**
     * The organizations you belong to, with your role in each — not every organization
     * there is. The list is the membership table read from your side.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $search = trim($request->string('search')->value());

        $memberships = OrganizationMembership::query()
            ->with('organization')
            ->withCount(['organization as sibling_count' => fn ($query) => $query])
            ->where('user_id', $user->id)
            ->when($search !== '', fn ($query) => $query->whereHas(
                'organization',
                fn ($organization) => $organization
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.mb_strtolower($search).'%']),
            ))
            ->get()
            ->sortBy(fn (OrganizationMembership $membership): string => $membership->organization->name)
            ->values();

        /** @var list<int> $organizationIds */
        $organizationIds = $memberships->pluck('organization_id')->all();

        $counts = $this->memberCountsFor($organizationIds);

        return Inertia::render('organizations/Index', [
            'organizations' => $memberships
                ->map(fn (OrganizationMembership $membership): array => (new OrganizationResource(
                    $membership->organization,
                    $membership->role,
                    $counts[$membership->organization_id] ?? 0,
                ))->resolve())
                ->all(),
            'search' => $search,
        ]);
    }

    public function store(OrganizationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $membership = $this->organizations->create(
            $user,
            $request->string('name')->value(),
            $request->has('slug') ? $request->string('slug')->value() : null,
        );

        return redirect()->route('organizations.leagues', $membership->organization_id);
    }

    public function show(OrganizationScope $scope): RedirectResponse
    {
        Gate::authorize(Permission::VIEW, $scope);

        return redirect()->route('organizations.leagues', $scope->organization()->id);
    }

    public function update(OrganizationRequest $request, OrganizationScope $scope): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $this->organizations->rename(
            $scope->organization(),
            $request->string('name')->value(),
            $request->has('slug') ? $request->string('slug')->value() : null,
        );

        return back();
    }

    public function destroy(OrganizationScope $scope): RedirectResponse
    {
        Gate::authorize(Permission::OWN, $scope);

        $this->organizations->delete($scope->organization());

        return redirect()->route('organizations.index');
    }

    /**
     * One grouped query rather than a count per row. The dashboard is the first screen
     * anybody sees, and it is the easiest place in the application to write an N+1.
     *
     * @param  list<int>  $organizationIds
     * @return array<int, int>
     */
    private function memberCountsFor(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = OrganizationMembership::query()
            ->selectRaw('organization_id, COUNT(*) as total')
            ->whereIn('organization_id', $organizationIds)
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $counts;
    }
}
