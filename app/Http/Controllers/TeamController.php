<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
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

class TeamController extends Controller
{
    private const ORDERING = [
        'name' => 'teams.name',
        'slug' => 'teams.slug',
        'created_at' => 'teams.created_at',
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
        $builder = Team::query()->where('organization_id', $scope->organization()->id);

        if (($term = $query->searchTerm()) !== null) {
            $builder->where(fn ($group) => $group
                ->whereRaw('LOWER(teams.name) LIKE ?', ['%'.$term.'%'])
                ->orWhereRaw('LOWER(teams.short_name) LIKE ?', ['%'.$term.'%'])
                ->orWhereRaw('LOWER(teams.slug) LIKE ?', ['%'.$term.'%']));
        }

        $this->listing->sort($builder, $query, self::ORDERING, 'name');

        return Inertia::render('organizations/Clubs', [
            ...$this->tabs->props($scope),
            'clubs' => $this->listing->respond(
                $builder,
                $query,
                /** @param list<Team> $rows */
                static fn (array $rows): array => array_map(
                    static fn (Team $team): array => (new TeamResource($team))->resolve(),
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

    public function store(TeamRequest $request, OrganizationScope $scope): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        Team::query()->create([
            'organization_id' => $scope->organization()->id,
            'name' => $request->string('name')->value(),
            'slug' => $this->resolveSlug(
                $scope,
                $request->string('name')->value(),
                $request->has('slug') ? $request->string('slug')->value() : null,
            ),
            'short_name' => trim($request->string('short_name')->value()),
        ]);

        return back();
    }

    public function update(TeamRequest $request, OrganizationScope $scope, int $teamId): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $team = $this->team($scope, $teamId);
        $team->name = $request->string('name')->value();
        $team->short_name = trim($request->string('short_name')->value());

        $requestedSlug = $request->has('slug') ? $request->string('slug')->value() : null;

        if ($requestedSlug !== null && $requestedSlug !== $team->slug) {
            $team->slug = $this->resolveSlug($scope, $team->name, $requestedSlug, $team->id);
        }

        $team->save();

        return back();
    }

    public function destroy(OrganizationScope $scope, int $teamId): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $this->team($scope, $teamId)->delete();

        return back();
    }

    private function team(OrganizationScope $scope, int $teamId): Team
    {
        $team = Team::query()
            ->where('organization_id', $scope->organization()->id)
            ->find($teamId);

        if ($team === null) {
            throw new NotFoundHttpException;
        }

        return $team;
    }

    private function resolveSlug(
        OrganizationScope $scope,
        string $name,
        ?string $requested,
        ?int $exceptId = null,
    ): string {
        $source = $requested !== null && trim($requested) !== '' ? $requested : $name;

        return $this->slugs->uniqueSlug($source, static fn (string $candidate): bool => Team::query()
            ->where('organization_id', $scope->organization()->id)
            ->where('slug', $candidate)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists());
    }
}
