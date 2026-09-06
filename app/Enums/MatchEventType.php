<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchEventType: string
{
    case Goal = 'GOAL';
    case YellowCard = 'YELLOW_CARD';
    case RedCard = 'RED_CARD';
    case Substitution = 'SUBSTITUTION';

    /** A substitution names two players: the one going off and the one coming on. */
    public function needsRelatedPlayer(): bool
    {
        return $this === self::Substitution;
    }

    /**
     * The only event that touches the score, and it does so in the same transaction that
     * records it. There is no other way for a score to change.
     */
    public function movesTheScore(): bool
    {
        return $this === self::Goal;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
