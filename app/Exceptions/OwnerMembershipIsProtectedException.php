<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Ownership is not editable through the members API — not demoted, not removed, and never
 * granted to a second person. Every write path on a membership passes the same guard, so
 * the invariant is stated once rather than checked in two controllers, one of which would
 * eventually forget.
 */
class OwnerMembershipIsProtectedException extends AuthorizationException
{
    public function __construct()
    {
        parent::__construct('Ownership cannot be changed through the members list.');
    }
}
