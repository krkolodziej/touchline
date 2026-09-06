<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\OrganizationMembership;
use Database\Factories\OrganizationFactory;
use Database\Factories\OrganizationMembershipFactory;
use Database\Factories\UserFactory;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

it('adds an existing account as a member', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $admin = memberOf($organization, OrganizationRole::Admin);
    $colleague = UserFactory::new()->createOne(['email' => 'colleague@example.com']);

    actingAs($admin)->post("/organizations/{$organization->id}/members", [
        'email' => 'colleague@example.com',
        'role' => 'ADMIN',
    ])->assertRedirect();

    $membership = OrganizationMembership::query()
        ->where('user_id', $colleague->id)
        ->sole();

    expect($membership->role)->toBe(OrganizationRole::Admin);
});

/**
 * Not a 404. There is no invitation flow, so the address has to belong to somebody already —
 * and from the caller's point of view it is the value they typed that is wrong, not the
 * resource they asked for.
 */
it('reports an unknown address on the email field rather than as a missing page', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $admin = memberOf($organization, OrganizationRole::Admin);

    actingAs($admin)->post("/organizations/{$organization->id}/members", [
        'email' => 'nobody@example.com',
        'role' => 'MEMBER',
    ])->assertSessionHasErrors(['email' => 'No account uses this email address yet.']);
});

it('refuses to add the same person twice', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $admin = memberOf($organization, OrganizationRole::Admin);
    $colleague = UserFactory::new()->createOne(['email' => 'colleague@example.com']);

    OrganizationMembershipFactory::new()->createOne([
        'organization_id' => $organization->id,
        'user_id' => $colleague->id,
    ]);

    actingAs($admin)->post("/organizations/{$organization->id}/members", [
        'email' => 'colleague@example.com',
        'role' => 'MEMBER',
    ])->assertSessionHasErrors(['conflict' => 'That person is already a member.']);

    expect(OrganizationMembership::query()->where('user_id', $colleague->id)->count())->toBe(1);
});

/**
 * OWNER is not in the assignable list, and there is no endpoint anywhere that mints a second
 * one. An organization with two owners is a state nothing else knows how to reason about.
 */
it('will not mint a second owner', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $owner = memberOf($organization, OrganizationRole::Owner);
    UserFactory::new()->createOne(['email' => 'colleague@example.com']);

    actingAs($owner)->post("/organizations/{$organization->id}/members", [
        'email' => 'colleague@example.com',
        'role' => 'OWNER',
    ])->assertSessionHasErrors(['role' => 'Choose a valid role.']);

    expect(OrganizationMembership::query()->where('role', 'OWNER')->count())->toBe(1);
});

it('changes somebody else, and refuses to touch the owner', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $owner = memberOf($organization, OrganizationRole::Owner);
    $member = memberOf($organization);

    $ownerMembership = OrganizationMembership::query()->where('user_id', $owner->id)->sole();
    $memberMembership = OrganizationMembership::query()->where('user_id', $member->id)->sole();

    actingAs($owner)
        ->patch("/organizations/{$organization->id}/members/{$memberMembership->id}", ['role' => 'ADMIN'])
        ->assertRedirect();

    expect($memberMembership->fresh()?->role)->toBe(OrganizationRole::Admin);

    actingAs($owner)
        ->patch("/organizations/{$organization->id}/members/{$ownerMembership->id}", ['role' => 'ADMIN'])
        ->assertForbidden();

    expect($ownerMembership->fresh()?->role)->toBe(OrganizationRole::Owner);
});

it('will not remove the owner either', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $owner = memberOf($organization, OrganizationRole::Owner);

    $ownerMembership = OrganizationMembership::query()->where('user_id', $owner->id)->sole();

    actingAs($owner)
        ->delete("/organizations/{$organization->id}/members/{$ownerMembership->id}")
        ->assertForbidden();

    expect(OrganizationMembership::query()->count())->toBe(1);
});

it('removes a member', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $admin = memberOf($organization, OrganizationRole::Admin);
    $member = memberOf($organization);

    $membership = OrganizationMembership::query()->where('user_id', $member->id)->sole();

    actingAs($admin)
        ->delete("/organizations/{$organization->id}/members/{$membership->id}")
        ->assertRedirect();

    expect(OrganizationMembership::query()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('refuses every membership write from a plain member', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $member = memberOf($organization);
    $other = memberOf($organization);

    $membership = OrganizationMembership::query()->where('user_id', $other->id)->sole();

    actingAs($member)->post("/organizations/{$organization->id}/members", [
        'email' => 'x@example.com',
        'role' => 'MEMBER',
    ])->assertForbidden();

    actingAs($member)
        ->patch("/organizations/{$organization->id}/members/{$membership->id}", ['role' => 'ADMIN'])
        ->assertForbidden();

    actingAs($member)
        ->delete("/organizations/{$organization->id}/members/{$membership->id}")
        ->assertForbidden();
});

/**
 * The nesting in the URL is load-bearing, not decorative: a membership that exists but
 * belongs to another organization is as absent as one that never existed.
 */
it('treats a membership reached through the wrong organization as missing', function (): void {
    $mine = OrganizationFactory::new()->createOne();
    $theirs = OrganizationFactory::new()->createOne();

    $admin = memberOf($mine, OrganizationRole::Admin);
    $outsider = memberOf($theirs);

    $membership = OrganizationMembership::query()->where('user_id', $outsider->id)->sole();

    actingAs($admin)
        ->delete("/organizations/{$mine->id}/members/{$membership->id}")
        ->assertNotFound();

    expect($membership->fresh())->not->toBeNull();
});

it('lists the roster owners first', function (): void {
    $organization = OrganizationFactory::new()->createOne();

    memberOf($organization);
    memberOf($organization, OrganizationRole::Admin);
    $owner = memberOf($organization, OrganizationRole::Owner);

    actingAs($owner)->get("/organizations/{$organization->id}/members")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('organizations/Members')
            ->has('members', 3)
            ->where('members.0.role', 'OWNER')
            ->where('members.1.role', 'ADMIN')
            ->where('members.2.role', 'MEMBER')
            ->where('can_manage', true)
            ->where('can_delete', true));
});

it('tells a plain member it may not manage or delete', function (): void {
    $organization = OrganizationFactory::new()->createOne();
    $member = memberOf($organization);

    actingAs($member)->get("/organizations/{$organization->id}/members")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can_manage', false)
            ->where('can_delete', false));
});
