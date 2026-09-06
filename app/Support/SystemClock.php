<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * The real clock, behind an interface.
 *
 * Delegating to `now()` means `travelTo()` still works, so tests can pick a time the
 * ordinary Laravel way — but the domain declares that it needs a clock rather than reaching
 * for a global, which is what makes "when did this match kick off" a question with one
 * answer instead of one per call site.
 */
class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return now()->toDateTimeImmutable();
    }
}
