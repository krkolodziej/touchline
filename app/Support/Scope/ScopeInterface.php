<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\Organization;

/**
 * A resolved position inside one organization: which organization, and with what authority.
 *
 * A scope can only be built by ScopeFactory, and ScopeFactory can only build one from a
 * query that joins the caller's own membership. So a controller holding a scope is holding
 * proof that the caller belongs there — the check cannot be forgotten, because there is no
 * way to get the object without it.
 *
 * Later stages add LeagueScope, SeasonScope, SeasonTeamScope and FixtureScope, each carrying
 * the whole chain above it and each implementing this interface.
 */
interface ScopeInterface
{
    public function organization(): Organization;

    public function role(): OrganizationRole;
}
