<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\Fixture;
use App\Models\League;
use App\Models\Organization;
use App\Models\Season;

readonly class FixtureScope implements ScopeInterface
{
    public function __construct(
        private SeasonScope $parent,
        private Fixture $fixtureModel,
    ) {}

    public function fixture(): Fixture
    {
        return $this->fixtureModel;
    }

    public function season(): Season
    {
        return $this->parent->season();
    }

    public function seasonScope(): SeasonScope
    {
        return $this->parent;
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
