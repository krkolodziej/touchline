<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Support\Listing\Listing;
use App\Support\Listing\ListQuery;
use App\Support\OrganizationTabs;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlayerController extends Controller
{
    private const ORDERING = [
        'last_name' => 'players.last_name',
        'first_name' => 'players.first_name',
        'date_of_birth' => 'players.date_of_birth',
        'created_at' => 'players.created_at',
    ];

    public function __construct(
        private readonly Listing $listing,
        private readonly OrganizationTabs $tabs,
    ) {}

    public function index(Request $request, OrganizationScope $scope): Response
    {
        Gate::authorize(Permission::VIEW, $scope);

        $query = ListQuery::fromRequest($request);
        $builder = Player::query()->where('organization_id', $scope->organization()->id);

        if (($term = $query->searchTerm()) !== null) {
            // The third clause is the one that matters: people search for "Jan Kowalski",
            // and neither column on its own contains that.
            $builder->where(fn ($group) => $group
                ->whereRaw('LOWER(players.first_name) LIKE ?', ['%'.$term.'%'])
                ->orWhereRaw('LOWER(players.last_name) LIKE ?', ['%'.$term.'%'])
                ->orWhereRaw("LOWER(players.first_name || ' ' || players.last_name) LIKE ?", ['%'.$term.'%']));
        }

        $this->listing->sort($builder, $query, self::ORDERING, 'last_name');

        return Inertia::render('organizations/Players', [
            ...$this->tabs->props($scope),
            'players' => $this->listing->respond(
                $builder,
                $query,
                /** @param list<Player> $rows */
                static fn (array $rows): array => array_map(
                    static fn (Player $player): array => (new PlayerResource($player))->resolve(),
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

    public function store(PlayerRequest $request, OrganizationScope $scope): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        Player::query()->create([
            'organization_id' => $scope->organization()->id,
            'first_name' => trim($request->string('first_name')->value()),
            'last_name' => trim($request->string('last_name')->value()),
            'date_of_birth' => $request->date('date_of_birth'),
        ]);

        return back();
    }

    public function update(PlayerRequest $request, OrganizationScope $scope, int $playerId): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $player = $this->player($scope, $playerId);
        $player->first_name = trim($request->string('first_name')->value());
        $player->last_name = trim($request->string('last_name')->value());
        $player->date_of_birth = $request->date('date_of_birth');
        $player->save();

        return back();
    }

    public function destroy(OrganizationScope $scope, int $playerId): RedirectResponse
    {
        Gate::authorize(Permission::MANAGE, $scope);

        $this->player($scope, $playerId)->delete();

        return back();
    }

    private function player(OrganizationScope $scope, int $playerId): Player
    {
        $player = Player::query()
            ->where('organization_id', $scope->organization()->id)
            ->find($playerId);

        if ($player === null) {
            throw new NotFoundHttpException;
        }

        return $player;
    }
}
