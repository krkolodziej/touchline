<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Standings\StandingsCalculator;
use App\Enums\MatchStatus;
use App\Http\Resources\FixtureResource;
use App\Models\Fixture;
use App\Support\Scope\LeagueScope;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\Permission;
use App\Support\Scope\SeasonScope;
use App\Support\SeasonTabs;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The three read-only views of a season: the front page, the table, and the scorers.
 *
 * None of them has a counterpart that writes. Every number here is derived from match
 * events, so there is nothing to store and nothing that could be stored wrongly.
 */
class SeasonTableController extends Controller
{
    public function __construct(
        private readonly StandingsCalculator $standings,
        private readonly SeasonTabs $tabs,
    ) {}

    /**
     * The season's front page: what is happening now, who is top, who is scoring, what is
     * next. Every query it runs is one another tab runs too, so moving between them is warm.
     */
    public function overview(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): Response {
        Gate::authorize(Permission::VIEW, $season);

        $table = $this->standings->tableFor($season->season());
        $statistics = $this->standings->playerStatistics($season->season());

        $goals = Fixture::query()
            ->where('season_id', $season->season()->id)
            ->where('status', MatchStatus::Finished->value)
            ->selectRaw('COALESCE(SUM(home_score + away_score), 0) as total')
            ->value('total');

        return Inertia::render('seasons/Overview', [
            ...$this->tabs->props($season),
            'summary' => [
                'clubs' => count($table),
                'played' => Fixture::query()
                    ->where('season_id', $season->season()->id)
                    ->where('status', MatchStatus::Finished->value)
                    ->count(),
                'goals' => (int) $goals,
            ],
            'live' => $this->fixtures($season, [MatchStatus::Live], 'asc', 5),
            'upcoming' => $this->fixtures($season, [MatchStatus::Scheduled], 'asc', 5),
            'top_of_the_table' => array_slice($table, 0, 5),
            'leading_scorers' => array_slice(
                array_values(array_filter(
                    $statistics,
                    static fn (array $row): bool => $row['goals'] > 0,
                )),
                0,
                5,
            ),
        ]);
    }

    public function table(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): Response {
        Gate::authorize(Permission::VIEW, $season);

        return Inertia::render('seasons/Table', [
            ...$this->tabs->props($season),
            'standings' => $this->standings->tableFor($season->season()),
        ]);
    }

    public function statistics(
        OrganizationScope $organization,
        LeagueScope $league,
        SeasonScope $season,
    ): Response {
        Gate::authorize(Permission::VIEW, $season);

        return Inertia::render('seasons/Statistics', [
            ...$this->tabs->props($season),
            'players' => $this->standings->playerStatistics($season->season()),
        ]);
    }

    /**
     * @param  list<MatchStatus>  $statuses
     * @return list<array<string, mixed>>
     */
    private function fixtures(SeasonScope $season, array $statuses, string $direction, int $limit): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Fixture::query()
            ->with(['homeTeam', 'awayTeam'])
            ->where('season_id', $season->season()->id)
            ->whereIn('status', array_map(static fn (MatchStatus $s): string => $s->value, $statuses))
            ->orderBy('kick_off_at', $direction)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(static fn (Fixture $fixture): array => (new FixtureResource($fixture))->resolve())
            ->values()
            ->all();

        return $rows;
    }
}
