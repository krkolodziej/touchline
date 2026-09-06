<?php

declare(strict_types=1);

use Database\Factories\TeamFactory;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

use Tests\Support\Cast;

/**
 * The shared list contract, tested once on one resource rather than four times on four. The
 * machinery is the same object in every controller; what differs per resource is only which
 * columns it searches and which fields it will sort on, and those are tested where they live.
 */
function clubList(int $count = 25): Cast
{
    $cast = Cast::make();

    foreach (range(1, $count) as $index) {
        TeamFactory::new()->createOne([
            'organization_id' => $cast->organization->id,
            'name' => sprintf('Club %02d', $index),
            'slug' => sprintf('club-%02d', $index),
        ]);
    }

    return $cast;
}

/**
 * Paging is opt-in. Twelve clubs and eighteen players are the sizes this application actually
 * deals in, and an envelope around them is ceremony nobody asked for.
 */
it('returns a plain array when nobody asked for a page', function (): void {
    $cast = clubList();

    actingAs($cast->admin)->get($cast->url('/clubs'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('clubs', 25)
            ->where('clubs.0.name', 'Club 01'));
});

it('returns the envelope as soon as either page or page_size is sent', function (): void {
    $cast = clubList();

    actingAs($cast->admin)->get($cast->url('/clubs?page=1'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('clubs.count', 25)
            ->where('clubs.page', 1)
            ->where('clubs.page_size', 20)
            ->where('clubs.next', 2)
            ->where('clubs.previous', null)
            ->has('clubs.results', 20));

    actingAs($cast->admin)->get($cast->url('/clubs?page_size=10'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('clubs.page', 1)
            ->where('clubs.page_size', 10)
            ->has('clubs.results', 10));
});

it('says there is nothing after the last page', function (): void {
    $cast = clubList();

    actingAs($cast->admin)->get($cast->url('/clubs?page=2'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('clubs.next', null)
            ->where('clubs.previous', 1)
            ->has('clubs.results', 5));
});

/**
 * Refused rather than clamped. A page of ten thousand rows is either a mistake or somebody
 * reading the whole table through a URL, and silently returning a hundred instead makes the
 * first case invisible.
 */
it('refuses a page size outside the bounds instead of quietly clamping it', function (): void {
    $cast = clubList(3);

    actingAs($cast->admin)->get($cast->url('/clubs?page_size=0'))->assertStatus(422);
    actingAs($cast->admin)->get($cast->url('/clubs?page_size=1000'))->assertStatus(422);
    actingAs($cast->admin)->get($cast->url('/clubs?page=0'))->assertStatus(422);
});

/**
 * The alternative is a parameter that is silently ignored — which is how a list ends up in
 * the wrong order in production while every response still looks perfectly plausible.
 */
it('refuses an unknown ordering field and names the ones that work', function (): void {
    $cast = clubList(3);

    $response = actingAs($cast->admin)->get($cast->url('/clubs?order=password'));

    $response->assertStatus(400);
    expect($response->getContent())->toContain('name')->toContain('slug')->toContain('created_at');
});

it('reverses the order with a leading minus', function (): void {
    $cast = clubList();

    actingAs($cast->admin)->get($cast->url('/clubs?order=-name'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('clubs.0.name', 'Club 25'));
});

it('searches across more than one column, case-insensitively', function (): void {
    $cast = clubList(3);

    TeamFactory::new()->createOne([
        'organization_id' => $cast->organization->id,
        'name' => 'Resovia',
        'short_name' => 'RES',
        'slug' => 'resovia',
    ]);

    actingAs($cast->admin)->get($cast->url('/clubs?search=RESOV'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('clubs', 1));

    actingAs($cast->admin)->get($cast->url('/clubs?search=res'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('clubs', 1));
});

/**
 * Without it, two rows sharing a sort key can swap places between requests — and somebody
 * paging through the list sees one of them twice and never sees the other.
 */
it('keeps a stable order when the sort key does not decide it', function (): void {
    $cast = Cast::make();

    foreach (range(1, 4) as $ignored) {
        TeamFactory::new()->createOne([
            'organization_id' => $cast->organization->id,
            'name' => 'Identical',
        ]);
    }

    $first = actingAs($cast->admin)->get($cast->url('/clubs?search=identical'))->viewData('page');
    $second = actingAs($cast->admin)->get($cast->url('/clubs?search=identical'))->viewData('page');

    expect($first['props']['clubs'])->toBe($second['props']['clubs']);
});
