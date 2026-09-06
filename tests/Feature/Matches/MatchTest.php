<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Models\Fixture;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use Database\Factories\LeagueFactory;
use Database\Factories\PlayerFactory;
use Database\Factories\SeasonFactory;
use Database\Factories\SeasonTeamFactory;
use Database\Factories\TeamFactory;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

use Tests\Support\Cast;

/**
 * Two clubs with squads, a season, and one match between them — the smallest world in which
 * anything in this stage means something.
 */
final class Kickoff
{
    /**
     * @param  array<int, list<Player>>  $squads  keyed by team id
     */
    public function __construct(
        public readonly Cast $cast,
        public readonly Season $season,
        public readonly Fixture $fixture,
        public readonly Team $home,
        public readonly Team $away,
        public readonly array $squads,
        public readonly string $url,
    ) {}

    public static function make(): self
    {
        $cast = Cast::make();
        $league = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
        $season = SeasonFactory::new()->createOne(['league_id' => $league->id]);

        $clubs = [];
        $squads = [];

        foreach (['Stal', 'Resovia'] as $name) {
            $team = TeamFactory::new()->createOne([
                'organization_id' => $cast->organization->id,
                'name' => $name,
            ]);

            $registration = SeasonTeamFactory::new()->createOne([
                'season_id' => $season->id,
                'team_id' => $team->id,
            ]);

            $squads[$team->id] = [];

            foreach (range(1, 3) as $number) {
                $player = PlayerFactory::new()->createOne([
                    'organization_id' => $cast->organization->id,
                    'last_name' => "{$name}{$number}",
                ]);

                RosterEntry::query()->create([
                    'season_team_id' => $registration->id,
                    'player_id' => $player->id,
                    'shirt_number' => $number,
                ]);

                $squads[$team->id][] = $player;
            }

            $clubs[] = $team;
        }

        $fixture = Fixture::query()->create([
            'season_id' => $season->id,
            'home_team_id' => $clubs[0]->id,
            'away_team_id' => $clubs[1]->id,
            'round_number' => 1,
            'leg' => 1,
            'kick_off_at' => now()->addDay(),
            'status' => MatchStatus::Scheduled,
        ]);

        return new self(
            $cast,
            $season,
            $fixture,
            $clubs[0],
            $clubs[1],
            $squads,
            $cast->url("/leagues/{$league->id}/seasons/{$season->id}/fixtures/{$fixture->id}"),
        );
    }

    public function start(): self
    {
        actingAs($this->cast->admin)->post("{$this->url}/start");

        return $this;
    }

    /** @return list<Player> */
    public function squadOf(Team $team): array
    {
        return $this->squads[$team->id];
    }
}

it('walks a match from scheduled to full time', function (): void {
    $match = Kickoff::make();

    actingAs($match->cast->admin)->post("{$match->url}/start")->assertRedirect();
    expect($match->fixture->fresh()?->status)->toBe(MatchStatus::Live)
        ->and($match->fixture->fresh()?->started_at)->not->toBeNull();

    actingAs($match->cast->admin)->post("{$match->url}/finish")->assertRedirect();
    expect($match->fixture->fresh()?->status)->toBe(MatchStatus::Finished)
        ->and($match->fixture->fresh()?->finished_at)->not->toBeNull();
});

it('refuses a move the machine does not allow, and says what it would allow', function (): void {
    $match = Kickoff::make();

    actingAs($match->cast->admin)->post("{$match->url}/finish")
        ->assertSessionHasErrors('conflict');

    expect(session('errors')?->get('conflict')[0])
        ->toContain('live, cancelled, postponed');

    expect($match->fixture->fresh()?->status)->toBe(MatchStatus::Scheduled);
});

it('will not move a finished match at all', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    foreach (['start', 'cancel', 'postpone', 'reschedule'] as $verb) {
        actingAs($match->cast->admin)->post("{$match->url}/{$verb}")
            ->assertSessionHasErrors('conflict');
    }

    expect($match->fixture->fresh()?->status)->toBe(MatchStatus::Finished);
});

/**
 * A match postponed mid-play keeps the moment it originally kicked off, because that is the
 * one anybody would mean by "when did this start".
 */
it('keeps the original kick-off when a match restarts', function (): void {
    $match = Kickoff::make();

    Carbon::setTestNow('2026-05-01 15:00:00');
    actingAs($match->cast->admin)->post("{$match->url}/start");
    $started = $match->fixture->fresh()?->started_at;

    actingAs($match->cast->admin)->post("{$match->url}/postpone");

    Carbon::setTestNow('2026-05-08 15:00:00');
    actingAs($match->cast->admin)->post("{$match->url}/start");

    expect($match->fixture->fresh()?->started_at?->toDateTimeString())
        ->toBe($started?->toDateTimeString());

    Carbon::setTestNow();
});

/** A scheduled match claiming to have kicked off last Tuesday is a lie the table would read. */
it('clears the start time when a match goes back on the calendar', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/postpone");
    actingAs($match->cast->admin)->post("{$match->url}/reschedule")->assertRedirect();

    expect($match->fixture->fresh()?->started_at)->toBeNull()
        ->and($match->fixture->fresh()?->status)->toBe(MatchStatus::Scheduled);
});

/**
 * The rule the whole application is built around. The score is not a field anybody types
 * into; it moves in the same transaction that records the goal, so the number and the list
 * of goals under it cannot disagree.
 */
it('moves the score by exactly one when a goal is recorded', function (): void {
    $match = Kickoff::make()->start();
    $scorer = $match->squadOf($match->home)[0];

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 23,
        'team_id' => $match->home->id,
        'player_id' => $scorer->id,
    ])->assertRedirect();

    expect($match->fixture->fresh()?->home_score)->toBe(1)
        ->and($match->fixture->fresh()?->away_score)->toBe(0)
        ->and(MatchEvent::query()->count())->toBe(1);
});

it('leaves the score alone for a card or a substitution', function (): void {
    $match = Kickoff::make()->start();
    $squad = $match->squadOf($match->home);

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'YELLOW_CARD',
        'minute' => 30,
        'team_id' => $match->home->id,
        'player_id' => $squad[0]->id,
    ])->assertRedirect();

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'SUBSTITUTION',
        'minute' => 60,
        'team_id' => $match->home->id,
        'player_id' => $squad[0]->id,
        'related_player_id' => $squad[1]->id,
    ])->assertRedirect();

    expect($match->fixture->fresh()?->home_score)->toBe(0)
        ->and(MatchEvent::query()->count())->toBe(2);
});

/** A goal against a match that has not kicked off is not a goal, it is a mistake about which match. */
it('refuses an event unless the match is live', function (): void {
    $match = Kickoff::make();
    $scorer = $match->squadOf($match->home)[0];

    $goal = [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $scorer->id,
    ];

    actingAs($match->cast->admin)->post("{$match->url}/events", $goal)
        ->assertSessionHasErrors([
            'conflict' => 'Events can only be recorded while a match is live. This one is scheduled.',
        ]);

    $match->start();
    actingAs($match->cast->admin)->post("{$match->url}/events", $goal)->assertRedirect();
    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->admin)->post("{$match->url}/events", $goal)
        ->assertSessionHasErrors('conflict');

    expect(MatchEvent::query()->count())->toBe(1);
});

it('refuses a club that is not playing in this match', function (): void {
    $match = Kickoff::make()->start();

    $bystander = TeamFactory::new()->createOne([
        'organization_id' => $match->cast->organization->id,
    ]);

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $bystander->id,
        'player_id' => $match->squadOf($match->home)[0]->id,
    ])->assertSessionHasErrors(['team_id' => 'That club is not playing in this match.']);

    expect(MatchEvent::query()->count())->toBe(0);
});

/**
 * The check that stops a goal being credited to somebody who was not on the pitch. Being in
 * the organization is not enough, and being in *some* squad is not enough either.
 */
it('refuses a player who is not in that squad for that season', function (): void {
    $match = Kickoff::make()->start();

    $outsider = PlayerFactory::new()->createOne([
        'organization_id' => $match->cast->organization->id,
    ]);

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $outsider->id,
    ])->assertSessionHasErrors(['player_id' => "That player is not in this club's squad for this season."]);

    // And the other club's player, credited to this one, is the same refusal.
    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $match->squadOf($match->away)[0]->id,
    ])->assertSessionHasErrors('player_id');

    expect(MatchEvent::query()->count())->toBe(0);
});

it('insists a substitution names the player coming on', function (): void {
    $match = Kickoff::make()->start();
    $squad = $match->squadOf($match->home);

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'SUBSTITUTION',
        'minute' => 60,
        'team_id' => $match->home->id,
        'player_id' => $squad[0]->id,
    ])->assertSessionHasErrors(['related_player_id' => 'A substitution needs the player coming on.']);

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'SUBSTITUTION',
        'minute' => 60,
        'team_id' => $match->home->id,
        'player_id' => $squad[0]->id,
        'related_player_id' => $squad[0]->id,
    ])->assertSessionHasErrors(['related_player_id' => 'A player cannot be substituted for themselves.']);

    expect(MatchEvent::query()->count())->toBe(0);
});

it('refuses a second player on anything that is not a substitution', function (): void {
    $match = Kickoff::make()->start();
    $squad = $match->squadOf($match->home);

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $squad[0]->id,
        'related_player_id' => $squad[1]->id,
    ])->assertSessionHasErrors(['related_player_id' => 'Only a substitution involves a second player.']);

    expect(MatchEvent::query()->count())->toBe(0);
});

/**
 * The client keeps no copy of the rules, so the two cannot drift apart. A button that would
 * be refused is never drawn.
 */
it('tells the page what the server would accept right now', function (): void {
    $match = Kickoff::make();

    actingAs($match->cast->member)->get($match->url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('matches/Show')
            ->where('fixture.allowed_transitions', ['LIVE', 'CANCELLED', 'POSTPONED']));

    $match->start();

    actingAs($match->cast->member)->get($match->url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('fixture.allowed_transitions', ['FINISHED', 'CANCELLED', 'POSTPONED']));

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->member)->get($match->url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('fixture.allowed_transitions', []));
});

/** Two goals in the same minute are ordinary; without the tiebreaker they swap places. */
it('reads the timeline by minute, then by the order things were recorded', function (): void {
    $match = Kickoff::make()->start();
    $squad = $match->squadOf($match->home);

    foreach ([[45, 0], [12, 1], [45, 2]] as [$minute, $index]) {
        actingAs($match->cast->admin)->post("{$match->url}/events", [
            'type' => 'GOAL',
            'minute' => $minute,
            'team_id' => $match->home->id,
            'player_id' => $squad[$index]->id,
        ]);
    }

    actingAs($match->cast->member)->get($match->url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('events.0.minute', 12)
            ->where('events.1.minute', 45)
            ->where('events.2.minute', 45)
            ->where('events.1.player_id', $squad[0]->id)
            ->where('events.2.player_id', $squad[2]->id));
});

it('says which side of the timeline an event belongs on', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->away->id,
        'player_id' => $match->squadOf($match->away)[0]->id,
    ]);

    actingAs($match->cast->member)->get($match->url)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('events.0.home', false));
});

it('offers only the two squads playing', function (): void {
    $match = Kickoff::make();

    TeamFactory::new()->createOne(['organization_id' => $match->cast->organization->id]);

    actingAs($match->cast->member)->get($match->url)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('squads', 2)
            ->has('squads.0.players', 3));
});

it('lets a member watch a match but not run it', function (): void {
    $match = Kickoff::make();

    actingAs($match->cast->member)->get($match->url)->assertOk();
    actingAs($match->cast->member)->post("{$match->url}/start")->assertForbidden();

    $match->start();

    actingAs($match->cast->member)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $match->squadOf($match->home)[0]->id,
    ])->assertForbidden();
});

it('treats a match in another season as missing', function (): void {
    $mine = Kickoff::make();
    $theirs = Kickoff::make();

    $wrong = str_replace(
        (string) $mine->fixture->id,
        (string) $theirs->fixture->id,
        $mine->url,
    );

    actingAs($mine->cast->admin)->get($wrong)->assertNotFound();
});

/** There is no endpoint that edits or deletes an event, and that is the whole design. */
it('has no way to change an event once it is recorded', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $match->squadOf($match->home)[0]->id,
    ]);

    $event = MatchEvent::query()->sole();

    actingAs($match->cast->admin)->patch("{$match->url}/events/{$event->id}")->assertNotFound();
    actingAs($match->cast->admin)->delete("{$match->url}/events/{$event->id}")->assertNotFound();
});

/**
 * A club whose goals are still on record cannot be deleted out from under them. Deleting the
 * whole organization still works, because that path removes the events first.
 */
it('will not let a club be deleted while its goals are on record', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $match->squadOf($match->home)[0]->id,
    ]);

    expect(fn () => Team::query()->whereKey($match->home->id)->delete())
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('counts the registered clubs and the fixtures on the season tabs', function (): void {
    $match = Kickoff::make();

    actingAs($match->cast->member)
        ->get($match->cast->url("/leagues/{$match->season->league_id}/seasons/{$match->season->id}/squads"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts.clubs', 2)
            ->where('counts.fixtures', 1));

    expect(SeasonTeam::query()->count())->toBe(2);
});

/**
 * The RESTRICT above has a consequence one stage up.
 *
 * Deleting an organization cascades to its clubs, and a club whose goals are on record
 * refuses to go — so a competition that has actually been played becomes undeletable. The
 * fix is not to weaken the foreign key, which is doing exactly what it was put there for,
 * but to take the children down deliberately, deepest first.
 */
it('deletes an organization that has matches played in it', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $match->squadOf($match->home)[0]->id,
    ]);

    $owner = memberOf($match->cast->organization, App\Enums\OrganizationRole::Owner);

    actingAs($owner)->delete($match->cast->url())->assertRedirect('/dashboard');

    expect(App\Models\Organization::query()->count())->toBe(0)
        ->and(MatchEvent::query()->count())->toBe(0)
        ->and(Fixture::query()->count())->toBe(0)
        ->and(RosterEntry::query()->count())->toBe(0)
        ->and(SeasonTeam::query()->count())->toBe(0)
        ->and(Season::query()->count())->toBe(0)
        ->and(Team::query()->count())->toBe(0)
        ->and(Player::query()->count())->toBe(0);
});
