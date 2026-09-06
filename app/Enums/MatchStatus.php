<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The states a match can be in, and the moves between them.
 *
 * A table rather than a workflow library. The whole machine is five states and nine
 * transitions; a package would add a configuration file, a service and a vocabulary, and
 * the rule would then live in a place nobody reads. Here it is one match expression that
 * fits on a screen, and the client is told what it may do rather than keeping its own copy.
 */
enum MatchStatus: string
{
    case Scheduled = 'SCHEDULED';
    case Live = 'LIVE';
    case Finished = 'FINISHED';
    case Cancelled = 'CANCELLED';
    case Postponed = 'POSTPONED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Live, self::Cancelled, self::Postponed],
            // A postponed match goes back on the calendar, or straight on if the new date is
            // today. It can also be abandoned without ever being played.
            self::Postponed => [self::Scheduled, self::Live, self::Cancelled],
            self::Live => [self::Finished, self::Cancelled, self::Postponed],
            self::Finished, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * The statuses whose events count towards a player's tally.
     *
     * Deliberately not the same set the table counts. A goal scored ten minutes ago is a
     * goal, and the scorer list says so straight away — but the three points are not awarded
     * until full time, because a match that is 2-1 at the hour is not a win yet. The
     * asymmetry is the point: one is a record of what has happened, the other is a
     * settlement of what it was worth.
     *
     * @return list<self>
     */
    public static function countedInStatistics(): array
    {
        return [self::Live, self::Finished];
    }

    /** @return list<string> */
    public static function countedInStatisticsValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::countedInStatistics());
    }

    /** @return list<string> */
    public function allowedTransitionValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, $this->allowedTransitions());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
