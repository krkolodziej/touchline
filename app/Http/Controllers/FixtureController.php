<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fixture\FixtureGenerator;
use App\Domain\Fixture\RoundRobinScheduler;
use App\Enums\MatchStatus;
use App\Http\Requests\GenerateFixturesRequest;
use App\Http\Resources\FixtureResource;
use App\Models\Fixture;
use App\Models\SeasonTeam;
use App\Support\Scope\LeagueScope;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use App\Support\Scope\SeasonScope;
use App\Support\SeasonTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FixtureController extends Controller
{
    public function __construct(
        private readonly FixtureGenerator $generator,
        private readonly RoundRobinScheduler $scheduler,
        private readonly SeasonTabs $tabs,
    ) {}

    public function index(
        Request $request,
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): Response {
        Gate::authorize(Permission::VIEW, $season);

        $builder = Fixture::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('season_id', $season->season()->id);

        if ($request->filled('round')) {
            $builder->where('round_number', $request->integer('round'));
        }

        if ($request->filled('team')) {
            $teamId = $request->integer('team');
            $builder->where(fn ($group) => $group
                ->where('home_team_id', $teamId)
                ->orWhere('away_team_id', $teamId));
        }

        if ($request->filled('status')) {
            $builder->whereIn('status', $this->statuses($request->string('status')->value()));
        }

        $fixtures = $builder
            ->orderBy('round_number')
            ->orderBy('leg')
            ->orderBy('id')
            ->get();

        $clubs = SeasonTeam::query()
            ->with('team')
            ->join('teams', 'teams.id', '=', 'season_teams.team_id')
            ->select('season_teams.*')
            ->where('season_teams.season_id', $season->season()->id)
            ->orderBy('teams.name')
            ->get();

        return Inertia::render('seasons/Fixtures', [
            ...$this->tabs->props($season),
            'fixtures' => $fixtures
                ->map(fn (Fixture $fixture): array => (new FixtureResource($fixture))->resolve())
                ->values()
                ->all(),
            'clubs' => $clubs
                ->map(static fn (SeasonTeam $registration): array => [
                    'id' => $registration->team_id,
                    'name' => $registration->team->name,
                ])
                ->values()
                ->all(),
            'filters' => [
                'round' => $request->filled('round') ? $request->integer('round') : null,
                'team' => $request->filled('team') ? $request->integer('team') : null,
                'status' => $request->filled('status') ? $request->string('status')->value() : null,
            ],
            // So the dialog can say how many matches it is about to make, before it makes
            // them. Nobody should have to press a button to find out what it does.
            'round_counts' => [
                'single' => $this->scheduler->roundCount($clubs->count(), false),
                'double' => $this->scheduler->roundCount($clubs->count(), true),
            ],
        ]);
    }

    public function generate(
        GenerateFixturesRequest $request,
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $season);

        $this->generator->generate(
            $season->season(),
            $request->doubleRound(),
            $request->firstRoundOn(),
            $request->daysBetweenRounds(),
        );

        return back();
    }

    public function clear(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): RedirectResponse {
        Gate::authorize(Permission::MANAGE, $season);

        $this->generator->clear($season->season());

        return back();
    }

    /**
     * `?status=LIVE,FINISHED`. An unrecognised value is refused rather than treated as a
     * filter that quietly matches nothing — which looks exactly like a season with no
     * matches in it.
     *
     * @return list<string>
     */
    private function statuses(string $value): array
    {
        $requested = array_filter(array_map('trim', explode(',', $value)), static fn (string $part): bool => $part !== '');

        foreach ($requested as $status) {
            if (MatchStatus::tryFrom($status) === null) {
                throw new HttpException(400, sprintf(
                    'Unknown status "%s". Try one of: %s.',
                    $status,
                    implode(', ', MatchStatus::values()),
                ));
            }
        }

        return array_values($requested);
    }
}
