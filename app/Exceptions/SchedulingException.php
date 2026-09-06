<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A calendar cannot be built from what is there.
 *
 * Each of these is a state of the world rather than a bad value, which is why they are
 * conflicts and not validation failures: nothing about the request would be improved by
 * typing it differently.
 */
class SchedulingException extends ConflictException
{
    public static function notEnoughClubs(): self
    {
        return new self(
            'A season needs at least two registered clubs before it can have a calendar.',
            'not_enough_clubs',
        );
    }

    public static function duplicateClubs(): self
    {
        return new self('The same club is listed twice.', 'duplicate_clubs');
    }

    public static function alreadyGenerated(): self
    {
        return new self(
            'This season already has a calendar. Clear it before generating another.',
            'fixtures_already_generated',
        );
    }
}
