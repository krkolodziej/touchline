<?php

declare(strict_types=1);

namespace App\Domain\Club;

use App\Domain\Standings\StandingsCalculator;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;

/**
 * A club's history: every season it was entered in, and how it did.
 *
 * The per-season row is taken from the same table the season itself renders, rather than
 * from a second query with the points formula written out again. Two places that both
 * calculate points are two places that can disagree about them.
 */
class ClubProfile
{
    public function __construct(private readonly StandingsCalculator $standings) {}

    /**
     * @return array{seasons: list<array<string, mixed>>, latest_season_id: int|null, squad: list<array<string, mixed>>}
     */
    public function of(Team $team): array
    {
        $registrations = SeasonTeam::query()
            ->with(['season.league'])
            ->join('seasons', 'seasons.id', '=', 'season_teams.season_id')
            ->select('season_teams.*')
            ->where('season_teams.team_id', $team->id)
            ->orderByDesc('seasons.start_date')
            ->orderByDesc('seasons.id')
            ->get();

        $squadSizes = RosterEntry::query()
            ->selectRaw('season_team_id, COUNT(*) as total')
            ->whereIn('season_team_id', $registrations->pluck('id'))
            ->groupBy('season_team_id')
            ->pluck('total', 'season_team_id');

        $seasons = [];

        foreach ($registrations as $registration) {
            $season = $registration->season;
            $row = $this->rowFor($season, $team);

            $seasons[] = [
                'season_id' => $season->id,
                'season_name' => $season->name,
                'league_id' => $season->league_id,
                'league_name' => $season->league->name,
                'start_date' => $season->start_date->toDateString(),
                'squad_size' => (int) ($squadSizes[$registration->id] ?? 0),
                ...$row,
            ];
        }

        $latest = $registrations->first();

        return [
            'seasons' => $seasons,
            'latest_season_id' => $latest?->season_id,
            'squad' => $latest === null ? [] : $this->squadOf($latest),
        ];
    }

    /**
     * One season's line for one club, lifted straight out of that season's table — which is
     * also where the position comes from, since a position only means anything relative to
     * everybody else.
     *
     * @return array<string, mixed>
     */
    private function rowFor(Season $season, Team $team): array
    {
        foreach ($this->standings->tableFor($season) as $row) {
            if ($row['team_id'] === $team->id) {
                return $row;
            }
        }

        return [
            'position' => null,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'goal_difference' => 0,
            'points' => 0,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function squadOf(SeasonTeam $registration): array
    {
        /** @var list<array<string, mixed>> $squad */
        $squad = RosterEntry::query()
            ->with('player')
            ->join('players', 'players.id', '=', 'roster_entries.player_id')
            ->select('roster_entries.*')
            ->where('roster_entries.season_team_id', $registration->id)
            ->orderByRaw('CASE WHEN roster_entries.shirt_number IS NULL THEN 1 ELSE 0 END')
            ->orderBy('roster_entries.shirt_number')
            ->orderBy('players.last_name')
            ->get()
            ->map(static fn (RosterEntry $entry): array => [
                'id' => $entry->id,
                'player_id' => $entry->player_id,
                'player_name' => $entry->player->full_name,
                'shirt_number' => $entry->shirt_number,
                'position' => $entry->position?->value,
                'captain' => $entry->captain,
            ])
            ->values()
            ->all();

        return $squad;
    }
}
