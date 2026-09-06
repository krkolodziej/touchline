<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\ScopeFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Lets a controller ask for a scope the way it asks for a model.
 *
 * A signature of `show(OrganizationScope $scope)` reads as "give me this organization" and
 * means "give me this organization if the person asking is in it, and a 404 otherwise".
 * There is no second line to forget, and no way to write a controller that skips the check.
 *
 * This is a route binding rather than a container binding, and the difference matters twice.
 * A container binding does not consume the route parameter it stands for, so every id after
 * it in the URL lands one position to the left — which is silent, and which deleted the
 * wrong membership until a test caught it. And a route binding resolves in
 * SubstituteBindings, before the controller, which is where a 404 belongs.
 *
 * SubstituteBindings runs before the `auth` middleware, so an anonymous visitor reaches this
 * first. They are sent to sign in rather than told the id does not exist: they have not been
 * refused anything yet, they simply have not said who they are.
 */
class ScopeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('organization', fn (string $value, mixed $route): OrganizationScope => $this
            ->factory()
            ->organizationScope($this->caller(), (int) $value));
    }

    protected function factory(): ScopeFactory
    {
        return $this->app->make(ScopeFactory::class);
    }

    protected function caller(): User
    {
        $user = $this->app->make(Request::class)->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.', ['web'], route('sign-in'));
        }

        return $user;
    }
}
