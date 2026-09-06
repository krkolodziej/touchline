<?php

declare(strict_types=1);

namespace App\Domain\Match;

use App\Enums\MatchStatus;
use App\Exceptions\InvalidTransitionException;
use App\Jobs\MatchFinished;
use App\Models\Fixture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

/**
 * Moving a match through its states, and the side effects each move has.
 *
 * The rules live on the enum; this applies them and records the times. The clock is injected
 * rather than read from `now()`, so a test can say what time it is instead of asserting
 * against whatever the machine happened to think — which is the difference between a test
 * that proves the timestamp is set and one that proves it is set to the right thing.
 */
class MatchLifecycle
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function start(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Live);
    }

    public function finish(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Finished);
    }

    public function cancel(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Cancelled);
    }

    public function postpone(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Postponed);
    }

    public function reschedule(Fixture $fixture): void
    {
        $this->transition($fixture, MatchStatus::Scheduled);
    }

    public function transition(Fixture $fixture, MatchStatus $target): void
    {
        $from = $fixture->status;

        if (! $from->canTransitionTo($target)) {
            throw new InvalidTransitionException($from, $target);
        }

        DB::transaction(function () use ($fixture, $target): void {
            $fixture->status = $target;
            $this->applySideEffects($fixture, $target);
            $fixture->save();

            /**
             * Dispatched inside the transaction, and this is the whole reason the queue lives
             * in the same database as everything else.
             *
             * `dispatch()` on the database driver is an INSERT on this connection, so the job
             * row is committed with the status change or rolled back with it. There is no
             * window in which the notification exists and the result does not, and none in
             * which a rolled-back full time still tells twelve people the match is over.
             *
             * A Redis queue could not do this. It would need `afterCommit`, which narrows the
             * window without closing it — the process can still die between the commit and
             * the push.
             */
            if ($target === MatchStatus::Finished) {
                MatchFinished::dispatch($fixture->id);
            }
        });
    }

    private function applySideEffects(Fixture $fixture, MatchStatus $target): void
    {
        // PSR-20 speaks DateTimeImmutable; the column is a Carbon. One conversion, here,
        // rather than a cast at each of the three places below.
        $now = Carbon::instance($this->clock->now());

        match ($target) {
            // `??` and not `=`: a match that was postponed mid-play and then restarted keeps
            // the moment it originally kicked off, which is the one anybody would mean.
            MatchStatus::Live => $fixture->started_at ??= $now,
            MatchStatus::Finished => $fixture->finished_at = $now,
            // Back on the calendar means it has not started. Leaving a stale start time
            // behind would make a scheduled match claim to have kicked off last Tuesday.
            MatchStatus::Scheduled => $fixture->started_at = null,
            default => null,
        };
    }
}
