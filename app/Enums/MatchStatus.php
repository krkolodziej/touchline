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
