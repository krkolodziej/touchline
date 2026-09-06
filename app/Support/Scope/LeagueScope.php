<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\League;
use App\Models\Organization;

readonly class LeagueScope implements ScopeInterface
{
    public function __construct(
        private OrganizationScope $parent,
        private League $leagueModel,
    ) {}

    public function league(): League
    {
        return $this->leagueModel;
    }

    public function organizationScope(): OrganizationScope
    {
        return $this->parent;
    }

    public function organization(): Organization
    {
        return $this->parent->organization();
    }

    public function role(): OrganizationRole
    {
        return $this->parent->role();
    }
}
