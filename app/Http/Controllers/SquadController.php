<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Squad\SquadManager;
use App\Http\Requests\RegisterTeamRequest;
use App\Http\Requests\RosterEntryRequest;
use App\Http\Resources\RosterEntryResource;
use App\Http\Resources\SeasonTeamResource;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Support\Scope\LeagueScope;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use App\Support\Scope\SeasonScope;
use App\Support\Scope\SeasonTeamScope;
use App\Support\SeasonTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SquadController extends Controller
{
    public function __construct(
        private readonly SquadManager $squads,
        private readonly SeasonTabs $tabs,
    ) {}

    /**
     * The registered clubs, and the squad of whichever one is selected.
     *
     * Which club is selected lives in the URL — `?club=7` — so a squad is a page somebody
     * can send to a colleague rather than a state they have to describe.
     */
    public function index(
        Request $request,
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): Response {
        Gate::authorize(Permission::VIEW, $season);

        $registrations = SeasonTeam::query()
            ->with('team')
            ->join('teams', 'teams.id', '=', 'season_teams.team_id')
            ->select('season_teams.*')
            ->where('season_teams.season_id', $season->season()->id)
            ->orderBy('teams.name')
            ->orderBy('season_teams.id')
            ->get();

        /** @var list<int> $registrationIds */
        $registrationIds = array_values($registrations->pluck('id')->all());
        /** @var list<int> $registeredTeamIds */
        $registeredTeamIds = array_values($registrations->pluck('team_id')->all());

        $squadSizes = $this->squadSizesFor($registrationIds);
        $selectedId = $this->selectedRegistrationId($request, $registrationIds);

        return Inertia::render('seasons/Squads', [
            ...$this->tabs->props($season),
            'registrations' => $registrations
                ->map(fn (SeasonTeam $registration): array => (new SeasonTeamResource(
                    $registration,
                    $squadSizes[$registration->id] ?? 0,
                ))->resolve())
                ->all(),
            'selected_id' => $selectedId,
            'roster' => $selectedId === null ? [] : $this->rosterFor($selectedId),
            // Only the clubs not already entered, and only the players not already in the
            // selected squad. Offering a choice the server will refuse is a worse experience
            // than not offering it.
            'available_clubs' => $this->availableClubs($season, $registeredTeamIds),
            'available_players' => $selectedId === null ? [] : $this->availablePlayers($season, $selectedId),
        ]);
    }

    public function register(
        RegisterTeamRequest $request,
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $season);

        $team = Team::query()->find($request->integer('team_id'));

        // A club from another organization is not reported as belonging to one — that would
        // confirm it exists. It is simply not there.
        if ($team === null || $team->organization_id !== $season->organization()->id) {
            throw new NotFoundHttpException;
        }

        $registration = $this->squads->registerTeam($season->season(), $team);

        return back(fallback: '/')->with('selected_registration', $registration->id);
    }

    public function withdraw(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
        SeasonTeamScope $seasonTeam,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $season);

        $this->squads->withdrawTeam($seasonTeam->seasonTeam());

        return back();
    }

    public function addToSquad(
        RosterEntryRequest $request,
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
        SeasonTeamScope $seasonTeam,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $season);

        $player = Player::query()->find($request->integer('player_id'));

        if ($player === null || $player->organization_id !== $season->organization()->id) {
            throw new NotFoundHttpException;
        }

        $this->squads->addToSquad(
            $seasonTeam->seasonTeam(),
            $player,
            $request->shirtNumber(),
            $request->position(),
            $request->boolean('captain'),
        );

        return back();
    }

    public function updateEntry(
        RosterEntryRequest $request,
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
        SeasonTeamScope $seasonTeam,
        int $rosterEntryId,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $season);

        $this->squads->updateSquadEntry(
            $this->entry($seasonTeam, $rosterEntryId),
            $request->shirtNumber(),
            $request->position(),
            $request->boolean('captain'),
        );

        return back();
    }

    public function removeEntry(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
        SeasonTeamScope $seasonTeam,
        int $rosterEntryId,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $season);

        $this->squads->removeFromSquad($this->entry($seasonTeam, $rosterEntryId));

        return back();
    }

    private function entry(SeasonTeamScope $seasonTeam, int $rosterEntryId): RosterEntry
    {
        $entry = RosterEntry::query()
            ->where('season_team_id', $seasonTeam->seasonTeam()->id)
            ->find($rosterEntryId);

        if ($entry === null) {
            throw new NotFoundHttpException;
        }

        $entry->setRelation('seasonTeam', $seasonTeam->seasonTeam());

        return $entry;
    }

    /**
     * Numbered players first, in shirt order, then everybody else by name. That is how a
     * team sheet is read, and it puts the unnumbered — the ones still to be sorted out — at
     * the bottom where they are noticed.
     *
     * @return list<array<string, mixed>>
     */
    private function rosterFor(int $seasonTeamId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = RosterEntry::query()
            ->with('player')
            ->join('players', 'players.id', '=', 'roster_entries.player_id')
            ->select('roster_entries.*')
            ->where('roster_entries.season_team_id', $seasonTeamId)
            ->orderByRaw('CASE WHEN roster_entries.shirt_number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('roster_entries.shirt_number')
            ->orderBy('players.last_name')
            ->orderBy('roster_entries.id')
            ->get()
            ->map(fn (RosterEntry $entry): array => (new RosterEntryResource($entry))->resolve())
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @param  list<int>  $registrationIds
     * @return array<int, int>
     */
    private function squadSizesFor(array $registrationIds): array
    {
        if ($registrationIds === []) {
            return [];
        }

        /** @var array<int, int> $sizes */
        $sizes = RosterEntry::query()
            ->selectRaw('season_team_id, COUNT(*) as total')
            ->whereIn('season_team_id', $registrationIds)
            ->groupBy('season_team_id')
            ->pluck('total', 'season_team_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        return $sizes;
    }

    /**
     * @param  list<int>  $registeredTeamIds
     * @return list<array<string, mixed>>
     */
    private function availableClubs(SeasonScope $season, array $registeredTeamIds): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Team::query()
            ->where('organization_id', $season->organization()->id)
            ->whereNotIn('id', $registeredTeamIds)
            ->orderBy('name')
            ->get()
            ->map(static fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
            ])
            ->values()
            ->all();

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function availablePlayers(SeasonScope $season, int $seasonTeamId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Player::query()
            ->where('organization_id', $season->organization()->id)
            ->whereNotIn('id', RosterEntry::query()
                ->select('player_id')
                ->where('season_team_id', $seasonTeamId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(static fn (Player $player): array => [
                'id' => $player->id,
                'full_name' => $player->full_name,
            ])
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @param  list<int>  $registrationIds
     */
    private function selectedRegistrationId(Request $request, array $registrationIds): ?int
    {
        if ($registrationIds === []) {
            return null;
        }

        $requested = $request->integer('club');

        return in_array($requested, $registrationIds, true) ? $requested : $registrationIds[0];
    }
}
