<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\MatchStatus;

/**
 * The match is not in a state that allows this. The message names what it would allow, so
 * whoever asked does not have to go and look the rules up.
 */
class InvalidTransitionException extends ConflictException
{
    public function __construct(MatchStatus $from, MatchStatus $to)
    {
        $allowed = $from->allowedTransitionValues();

        parent::__construct(
            $allowed === []
                ? sprintf('A %s match cannot be changed any further.', strtolower($from->value))
                : sprintf(
                    'A %s match cannot become %s. It can become: %s.',
                    strtolower($from->value),
                    strtolower($to->value),
                    strtolower(implode(', ', $allowed)),
                ),
            'invalid_transition',
        );
    }
}
