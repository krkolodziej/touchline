<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\Scope\FixtureScope;
use App\Support\Scope\LeagueScope;
use App\Support\Scope\OrganizationScope;
use App\Support\Scope\ScopeFactory;
use App\Support\Scope\ScopeInterface;
use App\Support\Scope\SeasonScope;
use App\Support\Scope\SeasonTeamScope;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutedRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lets a controller ask for a scope the way it asks for a model.
 *
 * A signature of `show(OrganizationScope $organization, SeasonScope $season)` reads as "give
 * me this season" and means "give me this season if the person asking is in the organization
 * that runs it, and a 404 otherwise". There is no second line to forget, and no way to write
 * a controller that skips the check.
 *
 * Two things about route bindings are worth knowing before reading the controllers.
 *
 * They resolve in URL order, so a nested binder can read the parent that has already been
 * resolved and only has to establish that the child belongs to it.
 *
 * And Laravel hands route parameters to a controller **positionally**, so a signature has to
 * list every parameter the URL contains, in order, even the ones the method never touches.
 * A missing one does not fail — it silently shifts every argument after it one place left.
 * That is the same trap that had the members endpoint deleting the wrong row in stage 2, so
 * the parents are spelled out rather than skipped.
 *
 * SubstituteBindings runs before the `auth` middleware, so an anonymous visitor reaches this
 * first. They are sent to sign in rather than told the id does not exist: they have not been
 * refused anything yet, they simply have not said who they are.
 */
class ScopeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind(
            'organization',
            fn (string $value): OrganizationScope => $this->factory()
                ->organizationScope($this->caller(), (int) $value),
        );

        Route::bind(
            'league',
            fn (string $value, RoutedRequest $route): LeagueScope => $this->factory()
                ->leagueScope($this->parent($route, 'organization', OrganizationScope::class), (int) $value),
        );

        Route::bind(
            'season',
            fn (string $value, RoutedRequest $route): SeasonScope => $this->factory()
                ->seasonScope($this->parent($route, 'league', LeagueScope::class), (int) $value),
        );

        Route::bind(
            'fixture',
            fn (string $value, RoutedRequest $route): FixtureScope => $this->factory()
                ->fixtureScope($this->parent($route, 'season', SeasonScope::class), (int) $value),
        );

        Route::bind(
            'seasonTeam',
            fn (string $value, RoutedRequest $route): SeasonTeamScope => $this->factory()
                ->seasonTeamScope($this->parent($route, 'season', SeasonScope::class), (int) $value),
        );
    }

    protected function factory(): ScopeFactory
    {
        return $this->app->make(ScopeFactory::class);
    }

    /**
     * @template TScope of ScopeInterface
     *
     * @param  class-string<TScope>  $expected
     * @return TScope
     */
    protected function parent(RoutedRequest $route, string $name, string $expected): ScopeInterface
    {
        $parent = $route->parameter($name);

        // Only reachable by declaring a route whose nesting does not match the scope chain,
        // which is a programming mistake rather than a request anybody can make.
        if (! $parent instanceof $expected) {
            throw new NotFoundHttpException;
        }

        return $parent;
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
