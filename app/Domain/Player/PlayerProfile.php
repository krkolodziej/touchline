<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Enums\MatchStatus;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\RosterEntry;

/**
 * A player's career: every squad they have been in, and what they did there.
 *
 * The career totals are summed from the per-season rows rather than fetched by a second
 * query. One query means the total and the seasons under it cannot disagree — a page where
 * the headline says fourteen goals and the rows add up to thirteen is a page nobody can use.
 */
class PlayerProfile
{
    /**
     * @return array{seasons: list<array<string, mixed>>, totals: array<string, int>, current: array<string, mixed>|null}
     */
    public function of(Player $player): array
    {
        $entries = RosterEntry::query()
            ->with(['seasonTeam.season.league', 'seasonTeam.team'])
            ->join('season_teams', 'season_teams.id', '=', 'roster_entries.season_team_id')
            ->join('seasons', 'seasons.id', '=', 'season_teams.season_id')
            ->select('roster_entries.*')
            ->where('roster_entries.player_id', $player->id)
            ->orderByDesc('seasons.start_date')
            ->orderByDesc('seasons.id')
            ->get();

        $tallies = $this->talliesFor($player);
        $seasons = [];
        $totals = ['goals' => 0, 'yellow_cards' => 0, 'red_cards' => 0];

        foreach ($entries as $entry) {
            $season = $entry->seasonTeam->season;
            $team = $entry->seasonTeam->team;

            // Keyed on the pair, because a player can appear twice in one season under two
            // clubs — a transfer mid-season is two rows, not one.
            $key = "{$season->id}:{$team->id}";
            $tally = $tallies[$key] ?? ['goals' => 0, 'yellow_cards' => 0, 'red_cards' => 0];

            foreach ($totals as $field => $value) {
                $totals[$field] = $value + $tally[$field];
            }

            $seasons[] = [
                'season_id' => $season->id,
                'season_name' => $season->name,
                'league_id' => $season->league_id,
                'league_name' => $season->league->name,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'shirt_number' => $entry->shirt_number,
                'position' => $entry->position?->value,
                'captain' => $entry->captain,
                ...$tally,
            ];
        }

        $current = $entries->first();

        return [
            'seasons' => $seasons,
            'totals' => $totals,
            'current' => $current === null ? null : [
                'season_id' => $current->seasonTeam->season_id,
                'season_name' => $current->seasonTeam->season->name,
                'league_id' => $current->seasonTeam->season->league_id,
                'team_id' => $current->seasonTeam->team_id,
                'team_name' => $current->seasonTeam->team->name,
                'shirt_number' => $current->shirt_number,
                'position' => $current->position?->value,
                'captain' => $current->captain,
            ],
        ];
    }

    /**
     * Every season-and-club tally in one grouped query, keyed the same way the rows are.
     *
     * @return array<string, array{goals: int, yellow_cards: int, red_cards: int}>
     */
    private function talliesFor(Player $player): array
    {
        $rows = MatchEvent::query()
            ->join('fixtures', 'fixtures.id', '=', 'match_events.fixture_id')
            ->selectRaw('fixtures.season_id, match_events.team_id')
            ->selectRaw("SUM(CASE WHEN match_events.type = 'GOAL' THEN 1 ELSE 0 END) as goals")
            ->selectRaw("SUM(CASE WHEN match_events.type = 'YELLOW_CARD' THEN 1 ELSE 0 END) as yellow_cards")
            ->selectRaw("SUM(CASE WHEN match_events.type = 'RED_CARD' THEN 1 ELSE 0 END) as red_cards")
            ->where('match_events.player_id', $player->id)
            ->whereIn('fixtures.status', MatchStatus::countedInStatisticsValues())
            ->groupBy('fixtures.season_id', 'match_events.team_id')
            ->get();

        $tallies = [];

        foreach ($rows as $row) {
            $key = $row->getAttribute('season_id').':'.$row->getAttribute('team_id');

            $tallies[$key] = [
                'goals' => (int) $row->getAttribute('goals'),
                'yellow_cards' => (int) $row->getAttribute('yellow_cards'),
                'red_cards' => (int) $row->getAttribute('red_cards'),
            ];
        }

        return $tallies;
    }
}
