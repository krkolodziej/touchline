<?php

declare(strict_types=1);

namespace App\Domain\Standings;

/**
 * What one club did on one side of the pitch, over a season.
 *
 * Home and away are aggregated separately because SQL cannot sum a club's matches in one
 * pass when the club appears in either of two columns. Two passes and an addition is
 * cheaper, clearer, and does not need a UNION.
 */
readonly class SideAggregate
{
    public function __construct(
        public int $teamId,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goalsFor,
        public int $goalsAgainst,
    ) {}
}
