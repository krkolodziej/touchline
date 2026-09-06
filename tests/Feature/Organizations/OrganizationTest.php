<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Database\Factories\OrganizationFactory;
use Database\Factories\OrganizationMembershipFactory;
use Database\Factories\UserFactory;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('makes the creator its owner', function (): void {
    $user = UserFactory::new()->createOne();

    actingAs($user)->post('/organizations', ['name' => 'Podkarpacki ZPN'])
        ->assertRedirect();

    $organization = Organization::query()->sole();
    $membership = OrganizationMembership::query()->sole();

    expect($organization->name)->toBe('Podkarpacki ZPN')
        ->and($organization->created_by_id)->toBe($user->id)
        ->and($membership->role)->toBe(OrganizationRole::Owner)
        ->and($membership->user_id)->toBe($user->id);
});

/**
 * The Polish transliteration table, not the generic one: without it the accented letters are
 * dropped rather than folded down to their plain equivalents.
 */
it('derives a slug from the name, transliterating Polish letters', function (): void {
    actingAs(UserFactory::new()->createOne())
        ->post('/organizations', ['name' => 'Łódzki Związek Piłki Nożnej']);

    expect(Organization::query()->sole()->slug)->toBe('lodzki-zwiazek-pilki-noznej');
});

/**
 * Two organizations may legitimately be called the same thing, and somebody who never typed
 * a slug should not be shown an error about one.
 */
it('suffixes a slug that is taken rather than refusing the name', function (): void {
    $user = UserFactory::new()->createOne();

    actingAs($user)->post('/organizations', ['name' => 'City League']);
    actingAs($user)->post('/organizations', ['name' => 'City League']);
    actingAs($user)->post('/organizations', ['name' => 'City League']);

    expect(Organization::query()->orderBy('id')->pluck('slug')->all())
        ->toBe(['city-league', 'city-league-2', 'city-league-3']);
});

it('refuses a slug that is not slug-shaped', function (): void {
    actingAs(UserFactory::new()->createOne())
        ->post('/organizations', ['name' => 'City League', 'slug' => 'Not A Slug'])
        ->assertSessionHasErrors(['slug' => 'Use lowercase letters, numbers and single hyphens.']);
});

it('lists only the organizations you belong to', function (): void {
    $mine = OrganizationFactory::new()->createOne(['name' => 'Mine']);
    OrganizationFactory::new()->createOne(['name' => 'Not mine']);

    $user = memberOf($mine);

    actingAs($user)->get('/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('organizations/Index')
            ->has('organizations', 1)
            ->where('organizations.0.name', 'Mine')
            ->where('organizations.0.my_role', 'MEMBER')
            ->where('organizations.0.member_count', 1));
});

/**
 * The single most important behaviour in this stage. A 403 would confirm the id is real, and
 * iterating over ids while telling the two apart would map the whole application to somebody
 * with no account in it.
 */
it('answers 404, not 403, for an organization you are not in', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $stranger = UserFactory::new()->createOne();

    actingAs($stranger)->get("/organizations/{$organization->id}")->assertNotFound();
    actingAs($stranger)->patch("/organizations/{$organization->id}", ['name' => 'Renamed'])->assertNotFound();
    actingAs($stranger)->delete("/organizations/{$organization->id}")->assertNotFound();
    actingAs($stranger)->post("/organizations/{$organization->id}/members", [
        'email' => 'someone@example.com',
        'role' => 'MEMBER',
    ])->assertNotFound();
});

it('gives the same answer for an id that was never issued', function (): void {
    actingAs(UserFactory::new()->createOne())->get('/organizations/999999')->assertNotFound();
});

it('turns anonymous visitors away before it decides anything else', function (): void {
    $organization = OrganizationFactory::new()->createOne();

    get("/organizations/{$organization->id}")->assertRedirect('/sign-in');
});

it('lets an admin rename it', function (): void {
    $organization = OrganizationFactory::new()->createOne(['name' => 'Old']);
    $admin = memberOf($organization, OrganizationRole::Admin);

    actingAs($admin)->patch("/organizations/{$organization->id}", ['name' => 'New'])
        ->assertRedirect();

    expect($organization->fresh()?->name)->toBe('New');
});

it('refuses a rename from a plain member', function (): void {
    $organization = OrganizationFactory::new()->createOne(['name' => 'Old']);
    $member = memberOf($organization);

    actingAs($member)->patch("/organizations/{$organization->id}", ['name' => 'New'])
        ->assertForbidden();

    expect($organization->fresh()?->name)->toBe('Old');
});

/**
 * Renaming and deleting are not the same power. An administrator runs the competition; only
 * the owner can end it.
 */
it('lets only the owner delete it', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $admin = memberOf($organization, OrganizationRole::Admin);
    $owner = memberOf($organization, OrganizationRole::Owner);

    actingAs($admin)->delete("/organizations/{$organization->id}")->assertForbidden();
    expect(Organization::query()->count())->toBe(1);

    actingAs($owner)->delete("/organizations/{$organization->id}")->assertRedirect('/dashboard');
    expect(Organization::query()->count())->toBe(0);
});

it('takes the memberships with it when it goes', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    memberOf($organization);
    $owner = memberOf($organization, OrganizationRole::Owner);

    actingAs($owner)->delete("/organizations/{$organization->id}");

    expect(OrganizationMembership::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(3);
});

it('finds an organization by name or slug', function (): void {
    $user = UserFactory::new()->createOne();

    foreach (['Rzeszow District', 'Krakow District', 'Warsaw County'] as $name) {
        $organization = OrganizationFactory::new()->createOne([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
        ]);

        OrganizationMembershipFactory::new()->createOne([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
    }

    actingAs($user)->get('/dashboard?search=district')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('organizations', 2));

    actingAs($user)->get('/dashboard?search=WARSAW')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('organizations', 1));
});
