<?php

declare(strict_types=1);

namespace App\Enums;

enum PlayerPosition: string
{
    case Goalkeeper = 'GOALKEEPER';
    case Defender = 'DEFENDER';
    case Midfielder = 'MIDFIELDER';
    case Forward = 'FORWARD';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** Two letters, the way a team sheet writes them. */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Goalkeeper => 'GK',
            self::Defender => 'DF',
            self::Midfielder => 'MF',
            self::Forward => 'FW',
        };
    }
}
