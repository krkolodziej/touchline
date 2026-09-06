<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Support\Scope\Permission;
use App\Support\Scope\ScopeInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Two halves, deliberately separate.
 *
 * ScopeFactory answers "does this exist *for you*", and answers no with a 404. These gates
 * answer "what may you do with something that does exist", and answer no with a 403. Mixing
 * them is how an application ends up telling strangers which ids are real.
 *
 * Nothing here runs a query. The role rode in on the scope, put there by the membership
 * join that proved the caller belongs at all.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define(Permission::VIEW, fn (User $user, ScopeInterface $scope): bool => true);

        Gate::define(
            Permission::MANAGE,
            fn (User $user, ScopeInterface $scope): bool => $scope->role()->canManage(),
        );

        Gate::define(
            Permission::OWN,
            fn (User $user, ScopeInterface $scope): bool => $scope->role() === OrganizationRole::Owner,
        );
    }
}
