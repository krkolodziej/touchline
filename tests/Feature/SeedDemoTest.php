<?php

declare(strict_types=1);

use App\Console\Commands\SeedDemoCommand;
use App\Enums\MatchStatus;
use App\Enums\OrganizationRole;
use App\Models\Fixture;
use App\Models\MatchEvent;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * Slow, and worth it. This is the only test that runs the whole application end to end —
 * twelve clubs registered, a calendar generated, seventy-odd matches played through the real
 * lifecycle and the real recorder — so a rule broken anywhere breaks here.
 */
beforeEach(function (): void {
    seedDemo();
});

it('builds a league with something in every state', function (): void {
    $byStatus = Fixture::query()
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    expect(Team::query()->count())->toBe(12)
        ->and(Fixture::query()->count())->toBe(132)
        ->and((int) $byStatus[MatchStatus::Live->value])->toBe(1)
        ->and((int) $byStatus[MatchStatus::Cancelled->value])->toBe(1)
        ->and((int) $byStatus[MatchStatus::Postponed->value])->toBe(2)
        ->and((int) $byStatus[MatchStatus::Finished->value])->toBeGreaterThan(60);
});

/** No column on any screen should be a row of dashes. */
it('gives every screen something to show', function (): void {
    $byType = MatchEvent::query()
        ->selectRaw('type, COUNT(*) as total')
        ->groupBy('type')
        ->pluck('total', 'type');

    expect((int) $byType['GOAL'])->toBeGreaterThan(100)
        ->and((int) $byType['YELLOW_CARD'])->toBeGreaterThan(10)
        ->and((int) $byType['RED_CARD'])->toBeGreaterThan(0)
        ->and((int) $byType['SUBSTITUTION'])->toBeGreaterThan(50)
        ->and(Player::query()->whereNull('date_of_birth')->count())->toBe(0);
});

/** Running out of names has to be a crash, not two players quietly sharing one. */
it('gives every player a name nobody else has', function (): void {
    $players = Player::query()->count();

    $distinct = Player::query()
        ->selectRaw('COUNT(DISTINCT (first_name, last_name)) as total')
        ->value('total');

    expect($players)->toBeGreaterThanOrEqual(12 * 16)
        ->and((int) $distinct)->toBe($players);
});

it('gives every squad exactly one captain', function (): void {
    $squads = RosterEntry::query()->distinct()->count('season_team_id');
    $captains = RosterEntry::query()->where('captain', true)->count();

    expect($squads)->toBe(12)->and($captains)->toBe(12);
});

/**
 * Nothing is written straight into the score columns, so this is not a tautology: it says
 * that every goal recorded through the recorder moved the score it belonged to, and that no
 * score moved without one.
 */
it('has a score on every match that agrees with its own goals', function (): void {
    $counted = MatchStatus::countedInStatisticsValues();

    $fixtures = Fixture::query()->whereIn('status', $counted)->get();

    $goals = MatchEvent::query()
        ->where('type', 'GOAL')
        ->selectRaw('fixture_id, team_id, COUNT(*) as total')
        ->groupBy('fixture_id', 'team_id')
        ->get()
        ->groupBy('fixture_id');

    foreach ($fixtures as $fixture) {
        $forFixture = $goals->get($fixture->id, collect());

        $home = (int) ($forFixture->firstWhere('team_id', $fixture->home_team_id)?->getAttribute('total') ?? 0);
        $away = (int) ($forFixture->firstWhere('team_id', $fixture->away_team_id)?->getAttribute('total') ?? 0);

        expect($fixture->home_score)->toBe($home, "fixture {$fixture->id} home")
            ->and($fixture->away_score)->toBe($away, "fixture {$fixture->id} away");
    }

    // And a cancelled match keeps whatever it had — the rule is about live and finished.
    expect($fixtures)->not->toBeEmpty();
});

/**
 * The visitor is an administrator, not the owner, and that is the point: everything worth
 * demonstrating is open to an administrator, while deleting the organization needs OWNER —
 * so a button on the open internet cannot destroy the thing it opens.
 */
it('makes the visitor an administrator and nothing more', function (): void {
    $visitor = User::query()->where('email', SeedDemoCommand::VISITOR_EMAIL)->sole();
    $membership = OrganizationMembership::query()->where('user_id', $visitor->id)->sole();

    expect($membership->role)->toBe(OrganizationRole::Admin);

    $owner = User::query()->where('email', SeedDemoCommand::OWNER_EMAIL)->sole();
    $ownerMembership = OrganizationMembership::query()->where('user_id', $owner->id)->sole();

    expect($ownerMembership->role)->toBe(OrganizationRole::Owner);
});

/** It runs on every container start in production, so a second run has to be a no-op. */
it('does nothing on a second run, and rebuilds on --flush', function (): void {
    $before = Fixture::query()->pluck('home_score', 'id');

    seedDemo(flush: false);

    expect(Organization::query()->count())->toBe(1)
        ->and(Fixture::query()->pluck('home_score', 'id')->all())->toBe($before->all());

    seedDemo(flush: true);

    expect(Organization::query()->count())->toBe(1)
        ->and(Fixture::query()->count())->toBe(132);
});

/**
 * The same seed produces the same league on every machine, which is what makes the table
 * checkable against the results in the first place.
 */
it('produces the same results every time', function (): void {
    $first = Fixture::query()
        ->orderBy('round_number')
        ->orderBy('id')
        ->get()
        ->map(fn (Fixture $f): string => "{$f->round_number}:{$f->home_score}-{$f->away_score}")
        ->all();

    seedDemo(flush: true);

    $second = Fixture::query()
        ->orderBy('round_number')
        ->orderBy('id')
        ->get()
        ->map(fn (Fixture $f): string => "{$f->round_number}:{$f->home_score}-{$f->away_score}")
        ->all();

    expect($second)->toBe($first);
});

it('leaves a season somebody can actually walk through', function (): void {
    $season = Season::query()->sole();
    $organization = Organization::query()->sole();
    $owner = User::query()->where('email', SeedDemoCommand::OWNER_EMAIL)->sole();

    $base = "/organizations/{$organization->id}/leagues/{$season->league_id}/seasons/{$season->id}";

    foreach (['overview', 'table', 'statistics', 'fixtures', 'squads'] as $tab) {
        actingAs($owner)->get("{$base}/{$tab}")->assertOk();
    }

    $live = Fixture::query()->where('status', MatchStatus::Live->value)->sole();
    actingAs($owner)->get("{$base}/fixtures/{$live->id}")->assertOk();

    $team = Team::query()->firstOrFail();
    actingAs($owner)->get("/organizations/{$organization->id}/clubs/{$team->id}/profile")->assertOk();

    $player = Player::query()->firstOrFail();
    actingAs($owner)->get("/organizations/{$organization->id}/players/{$player->id}/profile")->assertOk();
});
