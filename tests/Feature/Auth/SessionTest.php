<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function (): void {
    RateLimiter::clear('sign-in:'.sha1('ada@example.com|127.0.0.1'));
    RateLimiter::clear('sign-in-from:'.sha1('127.0.0.1'));
});

it('shows the sign-in screen', function (): void {
    get('/sign-in')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/SignIn'));
});

it('signs in with the right password', function (): void {
    $user = UserFactory::new()->createOne(['email' => 'ada@example.com']);

    post('/sign-in', [
        'email' => 'ada@example.com',
        'password' => UserFactory::PASSWORD,
    ])->assertRedirect('/dashboard');

    assertAuthenticatedAs($user);
});

it('accepts the address in any capitalisation', function (): void {
    $user = UserFactory::new()->createOne(['email' => 'ada@example.com']);

    post('/sign-in', [
        'email' => 'ADA@Example.com',
        'password' => UserFactory::PASSWORD,
    ])->assertRedirect('/dashboard');

    assertAuthenticatedAs($user);
});

/**
 * The point of the assertion is that the two responses are the same. Telling a wrong
 * password apart from an address nobody has registered turns a list of email addresses into
 * a list of this application's users.
 */
it('answers a wrong password and an unknown address identically', function (): void {
    UserFactory::new()->createOne(['email' => 'ada@example.com']);

    post('/sign-in', ['email' => 'ada@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors(['credentials' => 'Those credentials do not match anything we hold.']);

    assertGuest();

    post('/sign-in', ['email' => 'nobody@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors(['credentials' => 'Those credentials do not match anything we hold.']);

    assertGuest();
});

it('stops accepting attempts after five wrong passwords, and says so', function (): void {
    UserFactory::new()->createOne(['email' => 'ada@example.com']);

    foreach (range(1, 5) as $ignored) {
        post('/sign-in', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['credentials' => 'Those credentials do not match anything we hold.']);
    }

    $response = post('/sign-in', ['email' => 'ada@example.com', 'password' => 'wrong']);

    $errors = session('errors');
    expect($errors)->not->toBeNull()
        ->and($errors->get('credentials')[0])->toStartWith('Too many sign-in attempts.');

    // The right password does not get through either, or the limit would only ever slow
    // down the person who already knows it.
    post('/sign-in', ['email' => 'ada@example.com', 'password' => UserFactory::PASSWORD]);
    assertGuest();

    $response->assertRedirect();
});

/**
 * Otherwise somebody who mistyped four times and then got it right stays four-fifths of the
 * way to a lockout for the rest of the minute.
 */
it('clears the counter once somebody signs in', function (): void {
    $user = UserFactory::new()->createOne(['email' => 'ada@example.com']);

    foreach (range(1, 4) as $ignored) {
        post('/sign-in', ['email' => 'ada@example.com', 'password' => 'wrong']);
    }

    post('/sign-in', ['email' => 'ada@example.com', 'password' => UserFactory::PASSWORD])
        ->assertRedirect('/dashboard');

    assertAuthenticatedAs($user);

    post('/sign-out');

    foreach (range(1, 5) as $ignored) {
        post('/sign-in', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['credentials' => 'Those credentials do not match anything we hold.']);
    }
});

it('signs out and sends you back to the sign-in screen', function (): void {
    $user = UserFactory::new()->createOne();

    actingAs($user)->post('/sign-out')->assertRedirect('/sign-in');

    assertGuest();
});

it('sends an anonymous visitor to the sign-in screen', function (): void {
    get('/dashboard')->assertRedirect('/sign-in');
});

it('keeps a signed-in visitor away from the sign-in screen', function (): void {
    actingAs(UserFactory::new()->createOne())
        ->get('/sign-in')
        ->assertRedirect('/dashboard');
});

it('tells every page who is signed in', function (): void {
    $user = UserFactory::new()->createOne(['email' => 'ada@example.com', 'first_name' => 'Ada']);

    actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('organizations/Index')
            ->where('auth.user.email', 'ada@example.com')
            ->where('auth.user.first_name', 'Ada'));
});

it('never sends the password hash to the browser', function (): void {
    actingAs(UserFactory::new()->createOne())
        ->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('auth.user.password'));

    expect((new User)->getHidden())->toContain('password');
});
