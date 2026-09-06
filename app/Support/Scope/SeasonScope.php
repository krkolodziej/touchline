<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\League;
use App\Models\Organization;
use App\Models\Season;

readonly class SeasonScope implements ScopeInterface
{
    public function __construct(
        private LeagueScope $parent,
        private Season $seasonModel,
    ) {}

    public function season(): Season
    {
        return $this->seasonModel;
    }

    public function league(): League
    {
        return $this->parent->league();
    }

    public function leagueScope(): LeagueScope
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
