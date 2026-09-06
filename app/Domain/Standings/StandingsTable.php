<?php

declare(strict_types=1);

namespace App\Domain\Standings;

/**
 * The league table, from the aggregates up.
 *
 * Nothing here touches a database, which is what lets the tie-breaking rules be tested
 * exhaustively against hand-written numbers. The table is never stored: it is computed from
 * the results every time it is asked for, so it cannot fall out of step with them.
 */
class StandingsTable
{
    public const POINTS_FOR_A_WIN = 3;

    public const POINTS_FOR_A_DRAW = 1;

    /**
     * @param  array<int, string>  $clubs  team id => name, every club registered for the season
     * @param  list<SideAggregate>  $aggregates
     * @return list<array<string, mixed>>
     */
    public static function build(array $clubs, array $aggregates): array
    {
        // Seeded from the registered clubs rather than from the results. A club that has not
        // played yet still appears, on zero — a club that vanishes from its own table until
        // it wins something is a table nobody trusts.
        $rows = [];

        foreach ($clubs as $teamId => $name) {
            $rows[$teamId] = [
                'team_id' => $teamId,
                'team_name' => $name,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
            ];
        }

        foreach ($aggregates as $side) {
            if (! isset($rows[$side->teamId])) {
                continue;
            }

            $rows[$side->teamId]['played'] += $side->played;
            $rows[$side->teamId]['won'] += $side->won;
            $rows[$side->teamId]['drawn'] += $side->drawn;
            $rows[$side->teamId]['lost'] += $side->lost;
            $rows[$side->teamId]['goals_for'] += $side->goalsFor;
            $rows[$side->teamId]['goals_against'] += $side->goalsAgainst;
        }

        $rows = array_map(static function (array $row): array {
            $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
            $row['points'] = $row['won'] * self::POINTS_FOR_A_WIN + $row['drawn'] * self::POINTS_FOR_A_DRAW;

            return $row;
        }, $rows);

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($rows);

        usort($rows, self::compare(...));

        $position = 0;

        return array_map(static function (array $row) use (&$position): array {
            $position++;

            return ['position' => $position, ...$row];
        }, $rows);
    }

    /**
     * Points, then goal difference, then goals scored — and then the club's name.
     *
     * The last two are not really tie-breakers, they are determinism. Two clubs level on all
     * three real criteria share a position by any football rule anybody uses, but a list has
     * to put one of them first, and it has to put the same one first every time. Otherwise
     * the table reshuffles between two page loads that show identical numbers.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function compare(array $a, array $b): int
    {
        return $b['points'] <=> $a['points']
            ?: $b['goal_difference'] <=> $a['goal_difference']
            ?: $b['goals_for'] <=> $a['goals_for']
            ?: $a['team_name'] <=> $b['team_name']
            ?: $a['team_id'] <=> $b['team_id'];
    }
}
