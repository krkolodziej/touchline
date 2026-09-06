<?php

declare(strict_types=1);

namespace App\Support\Scope;

/**
 * The three things anybody can be allowed to do inside an organization. The subject of each
 * is a scope, never a model — by the time one of these is asked, membership has already
 * been proven by the query that produced the scope, so the only open question is what that
 * membership permits.
 */
final class Permission
{
    /** Holding a scope is proof enough. */
    public const VIEW = 'organization.view';

    /** Owners and administrators: everything about the competition. */
    public const MANAGE = 'organization.manage';

    /** Owners only: deleting the organization itself. */
    public const OWN = 'organization.own';
}
