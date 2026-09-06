<?php

declare(strict_types=1);

use App\Domain\Fixture\FixturePairing;
use App\Domain\Fixture\RoundRobinScheduler;
use App\Exceptions\SchedulingException;

/**
 * No database, no models, no container. A scheduling bug shows up as "round 14 looks wrong"
 * weeks later, so this file has to be able to check every club count exhaustively — and it
 * can only do that if it runs in milliseconds.
 */
function scheduler(): RoundRobinScheduler
{
    return new RoundRobinScheduler;
}

/** @return list<int> */
function clubIds(int $count): array
{
    return range(1, $count);
}

it('pairs every club with every other exactly once', function (int $count): void {
    $pairings = scheduler()->schedule(clubIds($count));

    $keys = array_map(static fn (FixturePairing $p): string => $p->pairKey(), $pairings);

    expect($keys)->toHaveCount($count * ($count - 1) / 2)
        ->and(array_unique($keys))->toHaveCount(count($keys));
})->with([[2], [3], [4], [5], [6], [7], [8], [9], [10], [11], [12], [13], [16], [20]]);

it('never asks a club to play twice in one round', function (int $count): void {
    foreach (scheduler()->schedule(clubIds($count)) as $pairing) {
        $byRound[$pairing->roundNumber][] = $pairing->homeTeamId;
        $byRound[$pairing->roundNumber][] = $pairing->awayTeamId;
    }

    foreach ($byRound ?? [] as $round => $playing) {
        expect(array_unique($playing))->toHaveCount(count($playing), "round {$round}");
    }
})->with([[2], [3], [4], [5], [6], [7], [8], [11], [12], [13], [20]]);

it('uses n-1 rounds for an even number of clubs and n for an odd one', function (): void {
    expect(scheduler()->roundCount(12))->toBe(11)
        ->and(scheduler()->roundCount(12, true))->toBe(22)
        ->and(scheduler()->roundCount(11))->toBe(11)
        ->and(scheduler()->roundCount(11, true))->toBe(22);
});

/** Everybody needs a week off, so the rounds still number n. */
it('gives an odd club count a bye each round', function (): void {
    $pairings = scheduler()->schedule(clubIds(5));

    $rounds = array_unique(array_map(static fn (FixturePairing $p): int => $p->roundNumber, $pairings));

    expect($rounds)->toHaveCount(5)
        ->and($pairings)->toHaveCount(10);
});

it('doubles the calendar with the sides reversed', function (): void {
    $single = scheduler()->schedule(clubIds(4));
    $double = scheduler()->schedule(clubIds(4), true);

    expect($double)->toHaveCount(count($single) * 2);

    $rounds = count($single) / 2;

    foreach ($single as $index => $first) {
        $second = $double[count($single) + $index];

        expect($second->homeTeamId)->toBe($first->awayTeamId)
            ->and($second->awayTeamId)->toBe($first->homeTeamId)
            ->and($second->roundNumber)->toBe($first->roundNumber + $rounds)
            ->and($second->leg)->toBe(2);
    }
});

/**
 * The rule this whole class exists to get right.
 *
 * The obvious `(round + index) % 2` leaves the lowest-numbered club away in every single
 * round, because the two positions it can occupy land on the same parity — and nothing about
 * the calendar looks wrong until somebody counts. So this counts.
 */
it('splits home and away evenly enough for every club', function (int $count): void {
    $home = array_fill_keys(clubIds($count), 0);
    $away = $home;

    foreach (scheduler()->schedule(clubIds($count)) as $pairing) {
        $home[$pairing->homeTeamId]++;
        $away[$pairing->awayTeamId]++;
    }

    foreach (clubIds($count) as $club) {
        expect(abs($home[$club] - $away[$club]))->toBeLessThanOrEqual(1, "club {$club}");
    }
})->with([[4], [6], [8], [10], [12], [14], [16], [18], [20]]);

/** Over a double round it is not "close to even", it is exactly even. */
it('gives every club the same number of home and away games over a double round', function (): void {
    $home = array_fill_keys(clubIds(12), 0);

    foreach (scheduler()->schedule(clubIds(12), true) as $pairing) {
        $home[$pairing->homeTeamId]++;
    }

    expect(array_unique(array_values($home)))->toBe([11]);
});

/**
 * A generator whose output depends on the order rows came back in is impossible to reason
 * about and impossible to test twice.
 */
it('produces the same calendar whatever order the clubs arrive in', function (): void {
    $ordered = scheduler()->schedule([1, 2, 3, 4, 5, 6]);
    $shuffled = scheduler()->schedule([5, 2, 6, 1, 4, 3]);

    $describe = static fn (array $pairings): array => array_map(
        static fn (FixturePairing $p): string => "{$p->roundNumber}:{$p->homeTeamId}v{$p->awayTeamId}",
        $pairings,
    );

    expect($describe($shuffled))->toBe($describe($ordered));
});

it('refuses fewer than two clubs', function (): void {
    expect(fn () => scheduler()->schedule([1]))->toThrow(SchedulingException::class)
        ->and(fn () => scheduler()->schedule([]))->toThrow(SchedulingException::class);
});

it('refuses the same club listed twice', function (): void {
    expect(fn () => scheduler()->schedule([1, 2, 2]))->toThrow(SchedulingException::class);
});

it('numbers rounds from one', function (): void {
    $pairings = scheduler()->schedule(clubIds(6), true);

    /** @var non-empty-list<int> $rounds */
    $rounds = array_map(static fn (FixturePairing $p): int => $p->roundNumber, $pairings);

    expect(min($rounds))->toBe(1)->and(max($rounds))->toBe(10);
});
