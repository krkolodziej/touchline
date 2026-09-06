<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Http\Requests\SeasonRequest;
use App\Http\Resources\LeagueResource;
use App\Http\Resources\SeasonResource;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Support\Scope\LeagueScope;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use App\Support\Scope\SeasonScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    /**
     * A league's seasons, newest first — because the one somebody is here for is almost
     * always the current one, and the current one is the last one made.
     *
     * `$organization` is unused, and is here because Laravel hands route parameters to a
     * controller positionally: leaving it out would shift every argument after it one place
     * left, silently. See ScopeServiceProvider.
     */
    public function index(OrganizationScope $organization, LeagueScope $league): Response
    {
        Gate::authorize(Permission::VIEW, $league);

        $seasons = Season::query()
            ->where('league_id', $league->league()->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        /** @var list<int> $seasonIds */
        $seasonIds = array_values($seasons->pluck('id')->all());

        $clubCounts = $this->clubCountsFor($seasonIds);

        return Inertia::render('leagues/Show', [
            'organization' => [
                'id' => $league->organization()->id,
                'name' => $league->organization()->name,
            ],
            'league' => (new LeagueResource($league->league(), $seasons->count()))->resolve(),
            'seasons' => $seasons
                ->map(fn (Season $season): array => (new SeasonResource(
                    $season,
                    $clubCounts[$season->id] ?? 0,
                ))->resolve())
                ->all(),
            'can_manage' => $league->role()->canManage(),
        ]);
    }

    /**
     * A season is one page with tabs, and the overview is the front of it: the table, who is
     * scoring, what is being played now and what is next. It is what somebody came for.
     */
    public function show(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): RedirectResponse {
        Gate::authorize(Permission::VIEW, $season);

        return redirect()->route('seasons.overview', [
            $season->organization()->id,
            $season->league()->id,
            $season->season()->id,
        ]);
    }

    public function store(
        SeasonRequest $request,
        OrganizationScope $organization,
        LeagueScope $league,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $league);

        $this->guardNameIsFree($league, $request->string('name')->value());

        Season::query()->create([
            'league_id' => $league->league()->id,
            'name' => trim($request->string('name')->value()),
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
        ]);

        return back();
    }

    public function destroy(
        OrganizationScope $organization,
        LeagueScope $league,
        int $seasonId,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $league);

        Season::query()
            ->where('league_id', $league->league()->id)
            ->findOrFail($seasonId)
            ->delete();

        return back();
    }

    /**
     * Checked rather than left to the unique index, because an integrity violation surfaces
     * as a 500. The index is still the guarantee; this is the message.
     */
    private function guardNameIsFree(LeagueScope $league, string $name): void
    {
        $taken = Season::query()
            ->where('league_id', $league->league()->id)
            ->where('name', trim($name))
            ->exists();

        if ($taken) {
            throw new ConflictException(
                'This league already has a season with that name.',
                'season_name_taken',
            );
        }
    }

    /**
     * One grouped query rather than a count per row.
     *
     * @param  list<int>  $seasonIds
     * @return array<int, int>
     */
    private function clubCountsFor(array $seasonIds): array
    {
        if ($seasonIds === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = SeasonTeam::query()
            ->selectRaw('season_id, COUNT(*) as total')
            ->whereIn('season_id', $seasonIds)
            ->groupBy('season_id')
            ->pluck('total', 'season_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $counts;
    }
}
