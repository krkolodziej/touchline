<?php

declare(strict_types=1);

namespace App\Domain\Fixture;

use App\Exceptions\SchedulingException;
use App\Models\Fixture;
use App\Models\Season;
use App\Models\SeasonTeam;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The half of fixture generation that touches the database.
 *
 * The scheduler decides who plays whom; this decides when, writes it, and makes sure it can
 * only happen once.
 */
class FixtureGenerator
{
    public function __construct(private readonly RoundRobinScheduler $scheduler) {}

    /**
     * Generates the whole calendar in one transaction, or none of it.
     *
     * The row lock is the part worth explaining. Two people pressing "Generate" a second
     * apart — or one person double-clicking — would both read an empty calendar and both
     * start writing; the unique index would then reject the second one partway through,
     * leaving half a calendar behind. Locking the season row makes the second attempt wait
     * and then find the fixtures the first one wrote, which is the answer it should have got
     * in the first place.
     *
     * @return list<Fixture>
     */
    public function generate(
        Season $season,
        bool $doubleRound = true,
        ?CarbonImmutable $firstRoundOn = null,
        int $daysBetweenRounds = 7,
    ): array {
        return DB::transaction(function () use ($season, $doubleRound, $firstRoundOn, $daysBetweenRounds): array {
            Season::query()->whereKey($season->id)->lockForUpdate()->firstOrFail();

            if (Fixture::query()->where('season_id', $season->id)->exists()) {
                throw SchedulingException::alreadyGenerated();
            }

            /** @var list<int> $teamIds */
            $teamIds = array_values(SeasonTeam::query()
                ->where('season_id', $season->id)
                ->orderBy('team_id')
                ->pluck('team_id')
                ->all());

            $pairings = $this->scheduler->schedule($teamIds, $doubleRound);
            $kickOffs = $this->kickOffTimes($season, $firstRoundOn, $daysBetweenRounds);

            $now = now();
            $rows = array_map(static fn (FixturePairing $pairing): array => [
                'season_id' => $season->id,
                'home_team_id' => $pairing->homeTeamId,
                'away_team_id' => $pairing->awayTeamId,
                'round_number' => $pairing->roundNumber,
                'leg' => $pairing->leg,
                'kick_off_at' => $kickOffs[$pairing->roundNumber] ?? null,
                'status' => 'SCHEDULED',
                'home_score' => 0,
                'away_score' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], $pairings);

            // One insert rather than a hundred and thirty-two. A twelve-club double round is
            // 132 rows, and a round trip each would make the button feel broken.
            Fixture::query()->insert($rows);

            /** @var list<Fixture> $fixtures */
            $fixtures = array_values(Fixture::query()
                ->where('season_id', $season->id)
                ->orderBy('round_number')
                ->orderBy('id')
                ->get()
                ->all());

            return $fixtures;
        });
    }

    public function clear(Season $season): int
    {
        return Fixture::query()->where('season_id', $season->id)->delete();
    }

    /**
     * One kick-off time per round, spaced evenly, at three in the afternoon.
     *
     * A single time for a whole round rather than a time per match: an amateur league plays
     * its round on one afternoon, and inventing staggered times would be inventing
     * information nobody gave us. Anybody who needs a different time for one match can move
     * that match.
     *
     * @return array<int, CarbonImmutable>
     */
    private function kickOffTimes(
        Season $season,
        ?CarbonImmutable $firstRoundOn,
        int $daysBetweenRounds,
    ): array {
        $start = ($firstRoundOn ?? CarbonImmutable::parse($season->start_date))->setTime(15, 0);
        $days = max(1, $daysBetweenRounds);

        $times = [];

        // An upper bound rather than a computed count: a round beyond the last one costs a
        // key nobody reads, and a round short of it costs a fixture with no date.
        for ($round = 1; $round <= 200; $round++) {
            $times[$round] = $start->addDays(($round - 1) * $days);
        }

        return $times;
    }
}
