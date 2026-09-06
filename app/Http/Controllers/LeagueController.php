<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LeagueRequest;
use App\Http\Resources\LeagueResource;
use App\Models\League;
use App\Support\Listing\Listing;
use App\Support\Listing\ListQuery;
use App\Support\OrganizationTabs;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use App\Support\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeagueController extends Controller
{
    /**
     * Wire name to column. A caller never names a column, so `order` cannot reach a field
     * this resource does not publish.
     */
    private const ORDERING = [
        'name' => 'leagues.name',
        'slug' => 'leagues.slug',
        'created_at' => 'leagues.created_at',
    ];

    public function __construct(
        private readonly Listing $listing,
        private readonly OrganizationTabs $tabs,
        private readonly SlugGenerator $slugs,
    ) {}

    public function index(Request $request, OrganizationScope $scope): Response
    {
        Gate::authorize(Permission::VIEW, $scope);

        $query = ListQuery::fromRequest($request);
        $builder = League::query()->where('organization_id', $scope->organization()->id);

        if (($term = $query->searchTerm()) !== null) {
            $builder->where(fn ($group) => $group
                ->whereRaw('LOWER(leagues.name) LIKE ?', ['%'.$term.'%'])
                ->orWhereRaw('LOWER(leagues.slug) LIKE ?', ['%'.$term.'%']));
        }

        $this->listing->sort($builder, $query, self::ORDERING, 'name');

        return Inertia::render('organizations/Leagues', [
            ...$this->tabs->props($scope),
            'leagues' => $this->listing->respond(
                $builder,
                $query,
                /** @param list<League> $rows */
                static fn (array $rows): array => array_map(
                    static fn (League $league): array => (new LeagueResource($league))->resolve(),
                    $rows,
                ),
            ),
            'query' => [
                'search' => $query->search,
                'page' => $query->page,
                'page_size' => $query->pageSize,
                'order' => $query->order,
            ],
        ]);
    }

    public function store(LeagueRequest $request, OrganizationScope $scope): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        League::query()->create([
            'organization_id' => $scope->organization()->id,
            'name' => $request->string('name')->value(),
            'slug' => $this->resolveSlug(
                $scope,
                $request->string('name')->value(),
                $request->has('slug') ? $request->string('slug')->value() : null,
            ),
            'description' => trim($request->string('description')->value()),
        ]);

        return back();
    }

    public function update(LeagueRequest $request, OrganizationScope $scope, int $leagueId): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $league = $this->league($scope, $leagueId);
        $league->name = $request->string('name')->value();
        $league->description = trim($request->string('description')->value());

        $requestedSlug = $request->has('slug') ? $request->string('slug')->value() : null;

        if ($requestedSlug !== null && $requestedSlug !== $league->slug) {
            $league->slug = $this->resolveSlug($scope, $league->name, $requestedSlug, $league->id);
        }

        $league->save();

        return back();
    }

    public function destroy(OrganizationScope $scope, int $leagueId): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $this->league($scope, $leagueId)->delete();

        return back();
    }

    /**
     * A league reached through the wrong organization is as absent as one that never
     * existed. The nesting in the URL is load-bearing.
     */
    private function league(OrganizationScope $scope, int $leagueId): League
    {
        $league = League::query()
            ->where('organization_id', $scope->organization()->id)
            ->find($leagueId);

        if ($league === null) {
            throw new NotFoundHttpException;
        }

        return $league;
    }

    private function resolveSlug(
        OrganizationScope $scope,
        string $name,
        ?string $requested,
        ?int $exceptId = null,
    ): string {
        $source = $requested !== null && trim($requested) !== '' ? $requested : $name;

        return $this->slugs->uniqueSlug($source, static fn (string $candidate): bool => League::query()
            ->where('organization_id', $scope->organization()->id)
            ->where('slug', $candidate)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists());
    }
}
