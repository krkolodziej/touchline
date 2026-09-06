<?php

declare(strict_types=1);

use App\Domain\Standings\SideAggregate;
use App\Domain\Standings\StandingsTable;

/**
 * Hand-written numbers, no database. The tie-breaking rules are the kind of thing that is
 * obviously right until two clubs finish level, so they are worth stating in arithmetic
 * somebody can check by eye.
 *
 * @param  array<int, string>  $clubs
 * @param  list<SideAggregate>  $aggregates
 * @return list<array<string, mixed>>
 */
function table(array $clubs, array $aggregates): array
{
    return StandingsTable::build($clubs, $aggregates);
}

function side(
    int $teamId,
    int $won = 0,
    int $drawn = 0,
    int $lost = 0,
    int $goalsFor = 0,
    int $goalsAgainst = 0,
): SideAggregate {
    return new SideAggregate(
        teamId: $teamId,
        played: $won + $drawn + $lost,
        won: $won,
        drawn: $drawn,
        lost: $lost,
        goalsFor: $goalsFor,
        goalsAgainst: $goalsAgainst,
    );
}

it('awards three for a win and one for a draw', function (): void {
    $rows = table([1 => 'Alpha'], [side(1, won: 4, drawn: 2, lost: 1)]);

    expect($rows[0]['points'])->toBe(14)
        ->and($rows[0]['played'])->toBe(7);
});

/** Home and away are aggregated separately, then added. */
it('adds the two sides of the pitch together', function (): void {
    $rows = table(
        [1 => 'Alpha'],
        [
            side(1, won: 3, drawn: 1, lost: 1, goalsFor: 9, goalsAgainst: 4),
            side(1, won: 1, drawn: 2, lost: 2, goalsFor: 5, goalsAgainst: 8),
        ],
    );

    expect($rows[0])->toMatchArray([
        'played' => 10,
        'won' => 4,
        'drawn' => 3,
        'lost' => 3,
        'goals_for' => 14,
        'goals_against' => 12,
        'goal_difference' => 2,
        'points' => 15,
    ]);
});

/**
 * A club that vanishes from its own table until it wins something is a table nobody trusts.
 */
it('lists a club that has not played, on zero', function (): void {
    $rows = table([1 => 'Alpha', 2 => 'Beta'], [side(1, won: 1, goalsFor: 2)]);

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toMatchArray([
            'team_name' => 'Beta',
            'position' => 2,
            'played' => 0,
            'points' => 0,
            'goal_difference' => 0,
        ]);
});

it('ranks on points before anything else', function (): void {
    $rows = table(
        [1 => 'Alpha', 2 => 'Beta'],
        [
            side(1, won: 1, goalsFor: 10, goalsAgainst: 0),
            side(2, won: 2, goalsFor: 2, goalsAgainst: 1),
        ],
    );

    // Beta has six points to Alpha's three, and a goal difference nine worse.
    expect(array_column($rows, 'team_name'))->toBe(['Beta', 'Alpha']);
});

it('breaks a tie on goal difference, then on goals scored', function (): void {
    $rows = table(
        [1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma'],
        [
            side(1, won: 2, goalsFor: 4, goalsAgainst: 2),
            side(2, won: 2, goalsFor: 8, goalsAgainst: 4),
            side(3, won: 2, goalsFor: 6, goalsAgainst: 2),
        ],
    );

    // All three on six points. Beta and Gamma are both +4, and Beta goes ahead on goals
    // scored — eight to six. Alpha is +2 and last.
    expect(array_column($rows, 'team_name'))->toBe(['Beta', 'Gamma', 'Alpha']);
});

/**
 * Level on all three real criteria is a shared position by any football rule anybody uses,
 * but a list has to put one of them first — and it has to put the same one first every time,
 * or the table reshuffles between two page loads showing identical numbers.
 */
it('falls back to the name, and then to the id, so the order never wobbles', function (): void {
    $clubs = [7 => 'Same', 3 => 'Same', 5 => 'Another'];
    $aggregates = [side(7, drawn: 1), side(3, drawn: 1), side(5, drawn: 1)];

    $first = table($clubs, $aggregates);
    $second = table(array_reverse($clubs, preserve_keys: true), array_reverse($aggregates));

    expect(array_column($first, 'team_id'))->toBe([5, 3, 7])
        ->and(array_column($second, 'team_id'))->toBe([5, 3, 7]);
});

it('numbers positions from one, in order', function (): void {
    $rows = table(
        [1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma'],
        [side(1, won: 1), side(2, won: 3), side(3, won: 2)],
    );

    expect(array_column($rows, 'position'))->toBe([1, 2, 3])
        ->and(array_column($rows, 'team_name'))->toBe(['Beta', 'Gamma', 'Alpha']);
});

it('ignores an aggregate for a club that is not registered', function (): void {
    $rows = table([1 => 'Alpha'], [side(1, won: 1), side(99, won: 5)]);

    expect($rows)->toHaveCount(1)->and($rows[0]['points'])->toBe(3);
});

it('handles a goal difference that is negative', function (): void {
    $rows = table([1 => 'Alpha'], [side(1, lost: 3, goalsFor: 1, goalsAgainst: 9)]);

    expect($rows[0]['goal_difference'])->toBe(-8)->and($rows[0]['points'])->toBe(0);
});
