<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('shows the sign-up screen', function (): void {
    get('/sign-up')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/SignUp'));
});

it('creates an account and signs it in', function (): void {
    post('/sign-up', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'ada@example.com')->sole();

    assertAuthenticatedAs($user);
    expect($user->full_name)->toBe('Ada Lovelace')
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
});

/**
 * The address is stored the way it will be compared. Without this, the same person can hold
 * two accounts that differ only in capitals, and the unique index will not have noticed.
 */
it('stores the email lower-cased, and refuses a second account differing only in case', function (): void {
    post('/sign-up', [
        'email' => '  Ada@Example.COM ',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect('/dashboard');

    expect(User::query()->sole()->email)->toBe('ada@example.com');

    post('/sign-out');

    post('/sign-up', [
        'email' => 'ADA@example.com',
        'password' => 'another-password',
        'password_confirmation' => 'another-password',
    ])->assertSessionHasErrors(['email' => 'An account with this email already exists.']);

    expect(User::query()->count())->toBe(1);
});

it('refuses a password the two boxes do not agree on', function (): void {
    post('/sign-up', [
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-batteries',
    ])->assertSessionHasErrors(['password' => 'The two passwords do not match.']);

    assertGuest();
});

it('refuses a password shorter than eight characters', function (): void {
    post('/sign-up', [
        'email' => 'ada@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors(['password' => 'Use at least 8 characters.']);

    assertGuest();
});

/**
 * A name is optional, so a member row has to render sensibly without one. The email is a
 * better fallback than an empty cell, which reads as a bug.
 */
it('accepts an account with no name and falls back to the email', function (): void {
    post('/sign-up', [
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect('/dashboard');

    expect(User::query()->sole()->full_name)->toBe('ada@example.com');
});
