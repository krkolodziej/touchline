<?php

declare(strict_types=1);

use App\Console\Commands\SeedDemoCommand;
use App\Models\Organization;
use App\Models\Season;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/** Absent rather than forbidden: with the switch off there is no such door here. */
it('is not there at all unless somebody switched it on', function (): void {
    config(['app.demo_login_enabled' => false]);

    post('/demo')->assertNotFound();
    assertGuest();

    get('/sign-in')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('demo_available', false));
});

/**
 * Seeding runs in the background on a cold start so the first request is not held up by it,
 * which leaves a minute or so where the button exists and the league does not. Saying so is
 * better than a login that half works.
 */
it('says the league is not ready yet rather than half signing somebody in', function (): void {
    config(['app.demo_login_enabled' => true]);

    post('/demo')->assertStatus(503);
    assertGuest();
});

it('lands a visitor inside the season, not on a list of organizations', function (): void {
    config(['app.demo_login_enabled' => true]);
    seedDemo();

    $organization = Organization::query()->where('slug', SeedDemoCommand::SLUG)->sole();
    $season = Season::query()->sole();

    post('/demo')->assertRedirect(
        "/organizations/{$organization->id}/leagues/{$season->league_id}/seasons/{$season->id}/overview",
    );

    assertAuthenticatedAs(User::query()->where('email', SeedDemoCommand::VISITOR_EMAIL)->sole());
});

/**
 * The whole safety of putting this on the open internet. Everything worth showing is open to
 * an administrator; deleting the organization is the one thing that is not.
 */
it('lets the visitor run the competition but not destroy it', function (): void {
    config(['app.demo_login_enabled' => true]);
    seedDemo();

    post('/demo');

    $organization = Organization::query()->where('slug', SeedDemoCommand::SLUG)->sole();

    // An administrator's powers.
    get("/organizations/{$organization->id}/leagues")->assertOk();
    post("/organizations/{$organization->id}/clubs", ['name' => 'Somebody New'])->assertRedirect();

    // And the one thing only an owner may do.
    delete("/organizations/{$organization->id}")->assertForbidden();

    expect(Organization::query()->count())->toBe(1);
});

it('offers the button on the sign-in page when it is switched on', function (): void {
    config(['app.demo_login_enabled' => true]);

    get('/sign-in')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('auth/SignIn')
        ->where('demo_available', true));
});
