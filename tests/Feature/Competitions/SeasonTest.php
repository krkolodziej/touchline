<?php

declare(strict_types=1);

use App\Models\Season;
use Database\Factories\LeagueFactory;
use Database\Factories\SeasonFactory;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

use Tests\Support\Cast;

function leagueFor(Cast $cast): string
{
    $league = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);

    return $cast->url("/leagues/{$league->id}");
}

it('creates a season with an optional end date', function (): void {
    $cast = Cast::make();
    $url = leagueFor($cast);

    actingAs($cast->admin)->post("{$url}/seasons", [
        'name' => '2026/27',
        'start_date' => '2026-08-01',
    ])->assertRedirect();

    $season = Season::query()->sole();

    expect($season->name)->toBe('2026/27')
        ->and($season->end_date)->toBeNull();
});

/**
 * Dates as dates. An instant would invent a midnight and a timezone neither value has, and a
 * reader an hour west would see a season starting the day before it does.
 */
it('emits the dates as dates', function (): void {
    $cast = Cast::make();
    $url = leagueFor($cast);

    actingAs($cast->admin)->post("{$url}/seasons", [
        'name' => '2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-11-30',
    ]);

    actingAs($cast->member)->get($url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seasons.0.start_date', '2026-03-01')
            ->where('seasons.0.end_date', '2026-11-30'));
});

it('refuses a season that ends before it starts', function (): void {
    $cast = Cast::make();
    $url = leagueFor($cast);

    actingAs($cast->admin)->post("{$url}/seasons", [
        'name' => '2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-07-01',
    ])->assertSessionHasErrors(['end_date' => 'A season cannot end before it starts.']);
});

it('refuses a season name the league already uses', function (): void {
    $cast = Cast::make();
    $url = leagueFor($cast);

    actingAs($cast->admin)->post("{$url}/seasons", ['name' => '2026', 'start_date' => '2026-03-01']);
    actingAs($cast->admin)->post("{$url}/seasons", ['name' => '2026', 'start_date' => '2026-03-01'])
        ->assertSessionHasErrors(['conflict' => 'This league already has a season with that name.']);

    expect(Season::query()->count())->toBe(1);
});

/** The same name in a different league is a different season, and perfectly ordinary. */
it('allows the same season name in another league', function (): void {
    $cast = Cast::make();

    $first = leagueFor($cast);
    $second = leagueFor($cast);

    actingAs($cast->admin)->post("{$first}/seasons", ['name' => '2026', 'start_date' => '2026-03-01']);
    actingAs($cast->admin)->post("{$second}/seasons", ['name' => '2026', 'start_date' => '2026-03-01'])
        ->assertRedirect();

    expect(Season::query()->count())->toBe(2);
});

it('lists seasons newest first', function (): void {
    $cast = Cast::make();
    $league = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);

    foreach (['2024', '2026', '2025'] as $name) {
        SeasonFactory::new()->createOne([
            'league_id' => $league->id,
            'name' => $name,
            'start_date' => "{$name}-03-01",
        ]);
    }

    actingAs($cast->member)->get($cast->url("/leagues/{$league->id}"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('leagues/Show')
            ->where('seasons.0.name', '2026')
            ->where('seasons.1.name', '2025')
            ->where('seasons.2.name', '2024'));
});

/**
 * The nesting in the URL is load-bearing: a season that exists but sits under another league
 * is as absent as one that never existed.
 */
it('treats a season reached through the wrong league as missing', function (): void {
    $cast = Cast::make();

    $mine = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
    $other = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
    $season = SeasonFactory::new()->createOne(['league_id' => $other->id]);

    actingAs($cast->member)
        ->get($cast->url("/leagues/{$mine->id}/seasons/{$season->id}/squads"))
        ->assertNotFound();

    actingAs($cast->member)
        ->get($cast->url("/leagues/{$other->id}/seasons/{$season->id}/squads"))
        ->assertOk();
});

it('sends a season straight to its overview', function (): void {
    $cast = Cast::make();
    $league = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
    $season = SeasonFactory::new()->createOne(['league_id' => $league->id]);

    $url = $cast->url("/leagues/{$league->id}/seasons/{$season->id}");

    actingAs($cast->member)->get($url)->assertRedirect($url.'/overview');
});

it('lets a member read a season and an admin write to it', function (): void {
    $cast = Cast::make();
    $url = leagueFor($cast);

    actingAs($cast->member)->get($url)->assertOk();
    actingAs($cast->member)->post("{$url}/seasons", [
        'name' => '2026',
        'start_date' => '2026-03-01',
    ])->assertForbidden();

    actingAs($cast->stranger)->get($url)->assertNotFound();
});
