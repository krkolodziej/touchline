<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\Notifier;
use App\Enums\MatchStatus;
use App\Enums\NotificationType;
use App\Models\Fixture;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A match reached full time, and the people who run the competition should hear about it.
 *
 * The job carries an id, not a copy of the match. A payload is a snapshot of a world that has
 * already moved on by the time anybody reads it — and a job that is retried tomorrow would
 * announce yesterday's score as though it were news.
 */
class MatchFinished implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $fixtureId) {}

    public function handle(Notifier $notifier): void
    {
        $fixture = Fixture::query()
            ->with(['homeTeam', 'awayTeam', 'season.league.organization'])
            ->find($this->fixtureId);

        // The world is allowed to have moved on between the dispatch and the handling: the
        // match may have been deleted, or reopened, or the whole organization removed. None
        // of those is an error, and none of them is worth a failed job — there is simply
        // nothing to announce any more.
        if ($fixture === null || $fixture->status !== MatchStatus::Finished) {
            return;
        }

        $season = $fixture->season;
        $organization = $season->league->organization;

        $notifier->deliver(
            $notifier->managersOf($organization),
            $organization,
            NotificationType::MatchFinished,
            (string) $fixture->id,
            sprintf(
                '%s %d–%d %s',
                $fixture->homeTeam->name,
                $fixture->home_score,
                $fixture->away_score,
                $fixture->awayTeam->name,
            ),
            sprintf('Round %d of %s %s has finished.', $fixture->round_number, $season->league->name, $season->name),
            sprintf(
                '/organizations/%d/leagues/%d/seasons/%d/fixtures/%d',
                $organization->id,
                $season->league_id,
                $season->id,
                $fixture->id,
            ),
        );
    }
}
