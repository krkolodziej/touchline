<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;

use function Pest\Laravel\actingAs;

use Tests\Support\Cast;

/**
 * The case this exists for.
 *
 * The live match page reloads itself every three seconds, and most of those reloads find
 * nothing has changed — the same bytes, sent again. Those are Inertia requests, which is why
 * the tests below are: an XHR reload carries no CSRF token, so two of them with nothing
 * changed in between really are identical, and the second one costs no body at all.
 *
 * A full page load is tagged too, but rarely benefits: its HTML carries the session's CSRF
 * token, so it differs between sessions by design.
 *
 * The version header has to match or Inertia answers 409 and asks the browser to reload —
 * which is right, and is also not what these tests are about. `Inertia::getVersion()` is only
 * populated once a request has been through the middleware, so the middleware is asked
 * directly instead.
 *
 * @param  array<string, string>  $extra
 * @return Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function inertiaGet(Cast $cast, string $path, array $extra = []): Illuminate\Testing\TestResponse
{
    return actingAs($cast->member)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            ...$extra,
        ])
        ->get($cast->url($path));
}

it('answers 304 when nothing has changed since last time', function (): void {
    $cast = Cast::make();

    $first = inertiaGet($cast, '/leagues');
    $first->assertOk();

    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    inertiaGet($cast, '/leagues', ['If-None-Match' => (string) $etag])
        ->assertStatus(304)
        ->assertNoContent(304);
});

it('sends the new version once something actually changes', function (): void {
    $cast = Cast::make();

    $etag = (string) inertiaGet($cast, '/leagues')->headers->get('ETag');

    actingAs($cast->admin)->post($cast->url('/leagues'), ['name' => 'District League']);

    inertiaGet($cast, '/leagues', ['If-None-Match' => $etag])->assertOk();
});

/**
 * Every one of these is somebody's own view of their own organization. A shared cache holding
 * one would hand it to the next person who asked.
 */
it('never lets a shared cache keep one', function (): void {
    $cast = Cast::make();

    expect(inertiaGet($cast, '/leagues')->headers->get('Cache-Control'))->toContain('private');
});

it('does not tag a write', function (): void {
    $cast = Cast::make();

    $response = actingAs($cast->admin)->post($cast->url('/leagues'), ['name' => 'District League']);

    expect($response->headers->get('ETag'))->toBeNull();
});

it('tags a full page load too', function (): void {
    $cast = Cast::make();

    expect(actingAs($cast->member)->get($cast->url('/leagues'))->headers->get('ETag'))
        ->not->toBeNull();
});
