<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\League;
use App\Models\Player;
use App\Models\Team;
use Database\Factories\LeagueFactory;
use Database\Factories\OrganizationFactory;
use Database\Factories\PlayerFactory;
use Database\Factories\TeamFactory;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

use Tests\Support\Cast;

it('sends the organization straight to its leagues', function (): void {
    $cast = Cast::make();

    actingAs($cast->member)->get($cast->url())->assertRedirect($cast->url('/leagues'));
});

it('creates a league and derives its slug', function (): void {
    $cast = Cast::make();

    actingAs($cast->admin)->post($cast->url('/leagues'), [
        'name' => 'District League',
        'description' => 'Sundays, from March.',
    ])->assertRedirect();

    $league = League::query()->sole();

    expect($league->slug)->toBe('district-league')
        ->and($league->description)->toBe('Sundays, from March.')
        ->and($league->organization_id)->toBe($cast->organization->id);
});

/**
 * Slugs are unique per organization, not globally. Two associations may each run a "District
 * League" and neither has to know the other exists.
 */
it('lets two organizations use the same league slug, but not one organization twice', function (): void {
    $cast = Cast::make();
    $other = OrganizationFactory::new()->createOne();
    $otherAdmin = memberOf($other, OrganizationRole::Admin);

    actingAs($cast->admin)->post($cast->url('/leagues'), ['name' => 'District League']);
    actingAs($otherAdmin)->post("/organizations/{$other->id}/leagues", ['name' => 'District League']);

    expect(League::query()->pluck('slug')->all())->toBe(['district-league', 'district-league']);

    actingAs($cast->admin)->post($cast->url('/leagues'), ['name' => 'District League']);

    expect(League::query()->where('organization_id', $cast->organization->id)->pluck('slug')->all())
        ->toBe(['district-league', 'district-league-2']);
});

it('registers a club, and falls back to the full name when no short one is given', function (): void {
    $cast = Cast::make();

    actingAs($cast->admin)->post($cast->url('/clubs'), ['name' => 'Stal Rzeszów'])
        ->assertRedirect();

    $club = Team::query()->sole();

    expect($club->slug)->toBe('stal-rzeszow')
        ->and($club->short_name)->toBe('')
        ->and($club->display_short_name)->toBe('Stal Rzeszów');
});

it('adds a player without a date of birth', function (): void {
    $cast = Cast::make();

    actingAs($cast->admin)->post($cast->url('/players'), [
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
    ])->assertRedirect();

    $player = Player::query()->sole();

    expect($player->date_of_birth)->toBeNull()
        ->and($player->age)->toBeNull()
        ->and($player->full_name)->toBe('Jan Kowalski');
});

/**
 * A date, not an instant. An instant would invent a midnight and a timezone the value does
 * not have, and a reader an hour west would see the day before.
 */
it('emits a date of birth as a date', function (): void {
    $cast = Cast::make();

    PlayerFactory::new()->createOne([
        'organization_id' => $cast->organization->id,
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
        'date_of_birth' => '1998-03-14',
    ]);

    actingAs($cast->member)->get($cast->url('/players'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('players.0.date_of_birth', '1998-03-14'));
});

it('refuses a date of birth in the future', function (): void {
    $cast = Cast::make();

    actingAs($cast->admin)->post($cast->url('/players'), [
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
        'date_of_birth' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors(['date_of_birth' => 'A date of birth cannot be in the future.']);
});

/** People search for "Jan Kowalski", and neither column on its own contains that. */
it('finds a player by their whole name', function (): void {
    $cast = Cast::make();

    PlayerFactory::new()->createOne([
        'organization_id' => $cast->organization->id,
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
    ]);
    PlayerFactory::new()->createOne([
        'organization_id' => $cast->organization->id,
        'first_name' => 'Piotr',
        'last_name' => 'Nowak',
    ]);

    actingAs($cast->member)->get($cast->url('/players?search=jan+kowalski'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('players', 1));
});

it('counts what is behind each tab', function (): void {
    $cast = Cast::make();

    LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
    TeamFactory::new()->count(3)->create(['organization_id' => $cast->organization->id]);
    PlayerFactory::new()->count(7)->create(['organization_id' => $cast->organization->id]);

    actingAs($cast->member)->get($cast->url('/leagues'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts.leagues', 1)
            ->where('counts.clubs', 3)
            ->where('counts.players', 7)
            ->where('counts.members', 2));
});

it('lets a member read every list and write to none of them', function (): void {
    $cast = Cast::make();

    foreach (['leagues', 'clubs', 'players'] as $collection) {
        actingAs($cast->member)->get($cast->url("/{$collection}"))->assertOk();
    }

    actingAs($cast->member)->post($cast->url('/leagues'), ['name' => 'District League'])->assertForbidden();
    actingAs($cast->member)->post($cast->url('/clubs'), ['name' => 'Stal'])->assertForbidden();
    actingAs($cast->member)->post($cast->url('/players'), [
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
    ])->assertForbidden();
});

it('cannot reach anything in another organization', function (): void {
    $cast = Cast::make();
    $other = OrganizationFactory::new()->createOne();

    $league = LeagueFactory::new()->createOne(['organization_id' => $other->id]);
    $club = TeamFactory::new()->createOne(['organization_id' => $other->id]);
    $player = PlayerFactory::new()->createOne(['organization_id' => $other->id]);

    // Reached through the wrong parent: as absent as an id that was never issued.
    actingAs($cast->admin)->delete($cast->url("/leagues/{$league->id}"))->assertNotFound();
    actingAs($cast->admin)->delete($cast->url("/clubs/{$club->id}"))->assertNotFound();
    actingAs($cast->admin)->delete($cast->url("/players/{$player->id}"))->assertNotFound();

    // And the organization itself is not visible at all.
    actingAs($cast->admin)->get("/organizations/{$other->id}/leagues")->assertNotFound();

    expect(League::query()->count())->toBe(1)
        ->and(Team::query()->count())->toBe(1)
        ->and(Player::query()->count())->toBe(1);
});

it('deletes everything inside an organization along with it', function (): void {
    $cast = Cast::make();

    LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
    TeamFactory::new()->createOne(['organization_id' => $cast->organization->id]);
    PlayerFactory::new()->createOne(['organization_id' => $cast->organization->id]);

    $owner = memberOf($cast->organization, OrganizationRole::Owner);

    actingAs($owner)->delete($cast->url())->assertRedirect('/dashboard');

    expect(League::query()->count())->toBe(0)
        ->and(Team::query()->count())->toBe(0)
        ->and(Player::query()->count())->toBe(0);
});
