<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Match\MatchEventRecorder;
use App\Domain\Match\MatchLifecycle;
use App\Enums\MatchStatus;
use App\Http\Requests\MatchEventRequest;
use App\Http\Resources\FixtureResource;
use App\Http\Resources\MatchEventResource;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Support\Scope\FixtureScope;
use App\Support\Scope\LeagueScope;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use App\Support\Scope\SeasonScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MatchController extends Controller
{
    /**
     * Which of the five verbs leads where. The client posts a verb; the server owns the
     * machine.
     */
    private const TRANSITIONS = [
        'start' => MatchStatus::Live,
        'finish' => MatchStatus::Finished,
        'cancel' => MatchStatus::Cancelled,
        'postpone' => MatchStatus::Postponed,
        'reschedule' => MatchStatus::Scheduled,
    ];

    public function __construct(
        private readonly MatchLifecycle $lifecycle,
        private readonly MatchEventRecorder $recorder,
    ) {}

    public function show(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
        FixtureScope $fixture,
    ): Response {
        Gate::authorize(Permission::VIEW, $fixture);

        $match = $fixture->fixture();

        return Inertia::render('matches/Show', [
            'organization' => ['id' => $organization->organization()->id, 'name' => $organization->organization()->name],
            'league' => ['id' => $league->league()->id, 'name' => $league->league()->name],
            'season' => ['id' => $season->season()->id, 'name' => $season->season()->name],
            'fixture' => (new FixtureResource($match))->resolve(),
            'events' => $this->timeline($fixture),
            // Only the two clubs playing, and only the players in their squads. A form that
            // offers a choice the server will refuse is a form that wastes somebody's time.
            'squads' => $this->squads($fixture),
            'can_manage' => $fixture->role()->canManage(),
        ]);
    }

    public function transition(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
        FixtureScope $fixture,
        string $verb,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $fixture);

        $target = self::TRANSITIONS[$verb] ?? throw new NotFoundHttpException;

        $this->lifecycle->transition($fixture->fixture(), $target);

        return back();
    }

    public function recordEvent(
        MatchEventRequest $request,
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
        FixtureScope $fixture,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $fixture);

        $this->recorder->record(
            $fixture->fixture(),
            $request->type(),
            $request->integer('minute'),
            $this->team($fixture, $request->integer('team_id')),
            $this->player($fixture, $request->integer('player_id')),
            $request->filled('related_player_id')
                ? $this->player($fixture, $request->integer('related_player_id'))
                : null,
        );

        return back();
    }

    /**
     * Ordered by minute, then by id. The tiebreaker matters more than it looks: two goals in
     * the same minute are ordinary, and without it they swap places between page loads.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(FixtureScope $fixture): array
    {
        /** @var list<array<string, mixed>> $events */
        $events = MatchEvent::query()
            ->with(['player', 'relatedPlayer'])
            ->where('fixture_id', $fixture->fixture()->id)
            ->orderBy('minute')
            ->orderBy('id')
            ->get()
            ->map(fn (MatchEvent $event): array => (new MatchEventResource($event, $fixture->fixture()))->resolve())
            ->values()
            ->all();

        return $events;
    }

    /** @return list<array<string, mixed>> */
    private function squads(FixtureScope $fixture): array
    {
        $match = $fixture->fixture();

        $registrations = SeasonTeam::query()
            ->with('team')
            ->where('season_id', $match->season_id)
            ->whereIn('team_id', [$match->home_team_id, $match->away_team_id])
            ->get();

        $entries = RosterEntry::query()
            ->with('player')
            ->join('players', 'players.id', '=', 'roster_entries.player_id')
            ->select('roster_entries.*')
            ->whereIn('roster_entries.season_team_id', $registrations->pluck('id'))
            ->orderByRaw('CASE WHEN roster_entries.shirt_number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('roster_entries.shirt_number')
            ->orderBy('players.last_name')
            ->get()
            ->groupBy('season_team_id');

        /** @var list<array<string, mixed>> $squads */
        $squads = $registrations
            ->map(static fn (SeasonTeam $registration): array => [
                'team_id' => $registration->team_id,
                'team_name' => $registration->team->name,
                'players' => $entries
                    ->get($registration->id, collect())
                    ->map(static fn (RosterEntry $entry): array => [
                        'id' => $entry->player_id,
                        'full_name' => $entry->player->full_name,
                        'shirt_number' => $entry->shirt_number,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return $squads;
    }

    /**
     * A club or player from outside this match is a 404, not an explanation of where they
     * do belong. The domain checks the same things again — this only stops the lookup from
     * reaching outside the organization at all.
     */
    private function team(FixtureScope $fixture, int $teamId): Team
    {
        $team = Team::query()
            ->where('organization_id', $fixture->organization()->id)
            ->find($teamId);

        if ($team === null) {
            throw new NotFoundHttpException;
        }

        return $team;
    }

    private function player(FixtureScope $fixture, int $playerId): Player
    {
        $player = Player::query()
            ->where('organization_id', $fixture->organization()->id)
            ->find($playerId);

        if ($player === null) {
            throw new NotFoundHttpException;
        }

        return $player;
    }
}
