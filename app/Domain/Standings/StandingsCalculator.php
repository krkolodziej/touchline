<?php

declare(strict_types=1);

namespace App\Domain\Standings;

use App\Enums\MatchStatus;
use App\Models\Fixture;
use App\Models\MatchEvent;
use App\Models\Season;
use App\Models\SeasonTeam;
use Illuminate\Support\Facades\DB;

/**
 * Everything a season can be asked to add up.
 *
 * The queries live here rather than in a controller because two screens want the same
 * numbers and a third wants them grouped differently — and because the one thing worth
 * being careful about is which matches count, which is a domain question and not a
 * presentation one.
 */
class StandingsCalculator
{
    /** @return list<array<string, mixed>> */
    public function tableFor(Season $season): array
    {
        return StandingsTable::build($this->clubsIn($season), $this->sideAggregates($season));
    }

    /**
     * Every registered club, by id. The table is seeded from this rather than from results.
     *
     * @return array<int, string>
     */
    public function clubsIn(Season $season): array
    {
        /** @var array<int, string> $clubs */
        $clubs = SeasonTeam::query()
            ->join('teams', 'teams.id', '=', 'season_teams.team_id')
            ->where('season_teams.season_id', $season->id)
            ->pluck('teams.name', 'teams.id')
            ->all();

        return $clubs;
    }

    /**
     * Two passes: one for the home column, one for the away column.
     *
     * Only finished matches. A match that is 2-1 at the hour is not a win, and putting it in
     * the table would mean a league that reorders itself on a Sunday afternoon and then
     * reorders itself back.
     *
     * @return list<SideAggregate>
     */
    public function sideAggregates(Season $season): array
    {
        $rows = [];

        foreach ([true, false] as $atHome) {
            $us = $atHome ? 'home' : 'away';
            $them = $atHome ? 'away' : 'home';

            $side = Fixture::query()
                ->selectRaw("{$us}_team_id as team_id")
                ->selectRaw('COUNT(*) as played')
                ->selectRaw("SUM(CASE WHEN {$us}_score > {$them}_score THEN 1 ELSE 0 END) as won")
                ->selectRaw("SUM(CASE WHEN {$us}_score = {$them}_score THEN 1 ELSE 0 END) as drawn")
                ->selectRaw("SUM(CASE WHEN {$us}_score < {$them}_score THEN 1 ELSE 0 END) as lost")
                ->selectRaw("SUM({$us}_score) as goals_for")
                ->selectRaw("SUM({$them}_score) as goals_against")
                ->where('season_id', $season->id)
                ->where('status', MatchStatus::Finished->value)
                ->groupBy("{$us}_team_id")
                ->get();

            foreach ($side as $row) {
                // Every aggregate is cast: PostgreSQL returns SUM() as a string through the
                // driver, and "3" + "1" is not what anybody wants a league table built from.
                $rows[] = new SideAggregate(
                    teamId: (int) $row->getAttribute('team_id'),
                    played: (int) $row->getAttribute('played'),
                    won: (int) $row->getAttribute('won'),
                    drawn: (int) $row->getAttribute('drawn'),
                    lost: (int) $row->getAttribute('lost'),
                    goalsFor: (int) $row->getAttribute('goals_for'),
                    goalsAgainst: (int) $row->getAttribute('goals_against'),
                );
            }
        }

        return $rows;
    }

    /**
     * Goals, yellows and reds per player, in one grouped query.
     *
     * Counts live matches as well as finished ones — see MatchStatus::countedInStatistics()
     * for why the two sets differ.
     *
     * @return list<array<string, mixed>>
     */
    public function playerStatistics(Season $season): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = MatchEvent::query()
            ->join('fixtures', 'fixtures.id', '=', 'match_events.fixture_id')
            ->join('players', 'players.id', '=', 'match_events.player_id')
            ->join('teams', 'teams.id', '=', 'match_events.team_id')
            ->selectRaw('players.id as player_id')
            ->selectRaw('players.first_name, players.last_name')
            ->selectRaw('teams.id as team_id, teams.name as team_name')
            ->selectRaw("SUM(CASE WHEN match_events.type = 'GOAL' THEN 1 ELSE 0 END) as goals")
            ->selectRaw("SUM(CASE WHEN match_events.type = 'YELLOW_CARD' THEN 1 ELSE 0 END) as yellow_cards")
            ->selectRaw("SUM(CASE WHEN match_events.type = 'RED_CARD' THEN 1 ELSE 0 END) as red_cards")
            ->where('fixtures.season_id', $season->id)
            ->whereIn('fixtures.status', MatchStatus::countedInStatisticsValues())
            ->groupBy('players.id', 'players.first_name', 'players.last_name', 'teams.id', 'teams.name')
            ->orderByDesc(DB::raw("SUM(CASE WHEN match_events.type = 'GOAL' THEN 1 ELSE 0 END)"))
            ->orderBy('players.last_name')
            ->orderBy('players.first_name')
            ->orderBy('players.id')
            ->get()
            ->map(static fn ($row): array => [
                'player_id' => (int) $row->getAttribute('player_id'),
                'first_name' => (string) $row->getAttribute('first_name'),
                'last_name' => (string) $row->getAttribute('last_name'),
                'team_id' => (int) $row->getAttribute('team_id'),
                'team_name' => (string) $row->getAttribute('team_name'),
                'goals' => (int) $row->getAttribute('goals'),
                'yellow_cards' => (int) $row->getAttribute('yellow_cards'),
                'red_cards' => (int) $row->getAttribute('red_cards'),
            ])
            ->values()
            ->all();

        return $rows;
    }
}
