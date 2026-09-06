<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\League;
use App\Models\Organization;
use App\Models\Season;
use App\Models\SeasonTeam;

readonly class SeasonTeamScope implements ScopeInterface
{
    public function __construct(
        private SeasonScope $parent,
        private SeasonTeam $seasonTeamModel,
    ) {}

    public function seasonTeam(): SeasonTeam
    {
        return $this->seasonTeamModel;
    }

    public function season(): Season
    {
        return $this->parent->season();
    }

    public function league(): League
    {
        return $this->parent->league();
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
