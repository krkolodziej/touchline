<?php

declare(strict_types=1);

namespace App\Support\Scope;

use App\Enums\OrganizationRole;
use App\Models\Organization;

readonly class OrganizationScope implements ScopeInterface
{
    public function __construct(
        private Organization $organizationModel,
        private OrganizationRole $roleValue,
    ) {}

    public function organization(): Organization
    {
        return $this->organizationModel;
    }

    public function role(): OrganizationRole
    {
        return $this->roleValue;
    }
}
