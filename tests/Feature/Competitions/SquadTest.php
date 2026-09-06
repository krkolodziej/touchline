<?php

declare(strict_types=1);

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use Database\Factories\LeagueFactory;
use Database\Factories\OrganizationFactory;
use Database\Factories\PlayerFactory;
use Database\Factories\SeasonFactory;
use Database\Factories\SeasonTeamFactory;
use Database\Factories\TeamFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

use Tests\Support\Cast;

/**
 * An organization with a league, a season, and a way to address them.
 */
final class SquadFixture
{
    public function __construct(
        public readonly Cast $cast,
        public readonly Season $season,
        public readonly string $url,
    ) {}

    public static function make(): self
    {
        $cast = Cast::make();
        $league = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
        $season = SeasonFactory::new()->createOne(['league_id' => $league->id]);

        return new self(
            $cast,
            $season,
            $cast->url("/leagues/{$league->id}/seasons/{$season->id}"),
        );
    }

    public function club(string $name = 'Stal Rzeszów'): Team
    {
        return TeamFactory::new()->createOne([
            'organization_id' => $this->cast->organization->id,
            'name' => $name,
        ]);
    }

    public function register(Team $team): SeasonTeam
    {
        return SeasonTeamFactory::new()->createOne([
            'season_id' => $this->season->id,
            'team_id' => $team->id,
        ]);
    }

    public function player(string $last = 'Kowalski'): Player
    {
        return PlayerFactory::new()->createOne([
            'organization_id' => $this->cast->organization->id,
            'last_name' => $last,
        ]);
    }
}

it('registers a club for a season', function (): void {
    $fixture = SquadFixture::make();
    $club = $fixture->club();

    actingAs($fixture->cast->admin)
        ->post("{$fixture->url}/teams", ['team_id' => $club->id])
        ->assertRedirect();

    expect(SeasonTeam::query()->count())->toBe(1);
});

it('refuses to register the same club twice', function (): void {
    $fixture = SquadFixture::make();
    $club = $fixture->club();

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams", ['team_id' => $club->id]);
    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams", ['team_id' => $club->id])
        ->assertSessionHasErrors(['conflict' => 'That club is already registered for this season.']);

    expect(SeasonTeam::query()->count())->toBe(1);
});

/**
 * Not "that club belongs to another organization" — that would confirm the id is real. The
 * ids are sequential and the endpoint is otherwise perfectly legitimate.
 */
it('treats a club from another organization as missing', function (): void {
    $fixture = SquadFixture::make();
    $elsewhere = TeamFactory::new()->createOne([
        'organization_id' => OrganizationFactory::new()->createOne()->id,
    ]);

    actingAs($fixture->cast->admin)
        ->post("{$fixture->url}/teams", ['team_id' => $elsewhere->id])
        ->assertNotFound();

    expect(SeasonTeam::query()->count())->toBe(0);
});

it('withdraws a club', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());

    actingAs($fixture->cast->admin)
        ->delete("{$fixture->url}/teams/{$registration->id}")
        ->assertRedirect();

    expect(SeasonTeam::query()->count())->toBe(0);
});

it('adds a player to a squad with a number and a position', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());
    $player = $fixture->player();

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $player->id,
        'shirt_number' => 9,
        'position' => 'FORWARD',
        'captain' => false,
    ])->assertRedirect();

    $entry = RosterEntry::query()->sole();

    expect($entry->shirt_number)->toBe(9)
        ->and($entry->position)->toBe(PlayerPosition::Forward)
        ->and($entry->captain)->toBeFalse();
});

/**
 * A squad list is built over a season, and refusing a player because nobody has decided his
 * number yet would just mean he is not entered at all.
 */
it('adds a player with neither a number nor a position', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $fixture->player()->id,
    ])->assertRedirect();

    $entry = RosterEntry::query()->sole();

    expect($entry->shirt_number)->toBeNull()->and($entry->position)->toBeNull();
});

it('refuses a shirt number already worn in that squad', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $fixture->player('Kowalski')->id,
        'shirt_number' => 9,
    ]);

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $fixture->player('Nowak')->id,
        'shirt_number' => 9,
    ])->assertSessionHasErrors(['shirt_number' => 'Number 9 is already worn in this squad.']);

    expect(RosterEntry::query()->count())->toBe(1);
});

/** NULLs are distinct in SQL, so any number of unnumbered players coexist. */
it('lets several players in one squad have no number', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());

    foreach (['Kowalski', 'Nowak', 'Wojcik'] as $name) {
        actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
            'player_id' => $fixture->player($name)->id,
        ])->assertRedirect();
    }

    expect(RosterEntry::query()->count())->toBe(3);
});

it('lets the same number be worn in two different squads', function (): void {
    $fixture = SquadFixture::make();
    $first = $fixture->register($fixture->club('Stal'));
    $second = $fixture->register($fixture->club('Resovia'));

    foreach ([$first, $second] as $registration) {
        actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
            'player_id' => $fixture->player()->id,
            'shirt_number' => 9,
        ])->assertRedirect();
    }

    expect(RosterEntry::query()->count())->toBe(2);
});

it('refuses to put the same player in one squad twice', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());
    $player = $fixture->player();

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $player->id,
    ]);

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $player->id,
    ])->assertSessionHasErrors(['conflict' => 'That player is already in this squad.']);

    expect(RosterEntry::query()->count())->toBe(1);
});

/**
 * The rule this stage exists to get right. A squad has exactly one captain, and refusing the
 * request would make the operator go and find out who currently holds it — a rule the
 * computer is better placed to keep than a person is.
 */
it('demotes the previous captain rather than refusing the new one', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $fixture->player('Kowalski')->id,
        'shirt_number' => 4,
        'captain' => true,
    ]);

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $fixture->player('Nowak')->id,
        'shirt_number' => 8,
        'captain' => true,
    ])->assertRedirect();

    $captains = RosterEntry::query()->where('captain', true)->get();

    expect($captains)->toHaveCount(1)
        ->and($captains->first()?->shirt_number)->toBe(8);
});

it('moves the armband on an update too', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());
    $base = "{$fixture->url}/teams/{$registration->id}";

    actingAs($fixture->cast->admin)->post("{$base}/roster", [
        'player_id' => $fixture->player('Kowalski')->id,
        'captain' => true,
    ]);
    actingAs($fixture->cast->admin)->post("{$base}/roster", [
        'player_id' => $fixture->player('Nowak')->id,
    ]);

    $second = RosterEntry::query()->orderByDesc('id')->firstOrFail();

    actingAs($fixture->cast->admin)->patch("{$base}/roster/{$second->id}", [
        'player_id' => $second->player_id,
        'captain' => true,
    ])->assertRedirect();

    expect(RosterEntry::query()->where('captain', true)->count())->toBe(1)
        ->and($second->fresh()?->captain)->toBeTrue();
});

/**
 * The application demotes rather than refuses, so this rule never fires through the
 * application. It is here because the guarantee is the index, not the code above it — and an
 * index nobody has watched refuse anything is an index nobody knows is there.
 */
it('has a database that refuses two captains outright', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());

    foreach (['Kowalski', 'Nowak'] as $name) {
        RosterEntry::query()->create([
            'season_team_id' => $registration->id,
            'player_id' => $fixture->player($name)->id,
            'captain' => $name === 'Kowalski',
        ]);
    }

    $second = RosterEntry::query()->where('captain', false)->sole();

    expect(fn () => DB::table('roster_entries')->where('id', $second->id)->update(['captain' => true]))
        ->toThrow(QueryException::class);
});

it('reads a squad numbered players first, unnumbered last', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());
    $base = "{$fixture->url}/teams/{$registration->id}";

    foreach ([['Zielinski', 7], ['Adamczyk', null], ['Nowak', 1]] as [$name, $number]) {
        actingAs($fixture->cast->admin)->post("{$base}/roster", [
            'player_id' => $fixture->player($name)->id,
            'shirt_number' => $number,
        ]);
    }

    actingAs($fixture->cast->member)->get("{$fixture->url}/squads?club={$registration->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seasons/Squads')
            ->where('roster.0.shirt_number', 1)
            ->where('roster.1.shirt_number', 7)
            ->where('roster.2.shirt_number', null));
});

it('offers only the clubs and players not already entered', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club('Stal'));
    $fixture->club('Resovia');

    $inSquad = $fixture->player('Kowalski');
    $fixture->player('Nowak');

    actingAs($fixture->cast->admin)->post("{$fixture->url}/teams/{$registration->id}/roster", [
        'player_id' => $inSquad->id,
    ]);

    actingAs($fixture->cast->member)->get("{$fixture->url}/squads?club={$registration->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('available_clubs', 1)
            ->where('available_clubs.0.name', 'Resovia')
            ->has('available_players', 1)
            ->where('available_players.0.full_name', fn (string $name): bool => str_contains($name, 'Nowak')));
});

it('refuses every squad write from a plain member', function (): void {
    $fixture = SquadFixture::make();
    $registration = $fixture->register($fixture->club());

    actingAs($fixture->cast->member)->get("{$fixture->url}/squads")->assertOk();

    actingAs($fixture->cast->member)
        ->post("{$fixture->url}/teams", ['team_id' => $fixture->club('Resovia')->id])
        ->assertForbidden();

    actingAs($fixture->cast->member)
        ->post("{$fixture->url}/teams/{$registration->id}/roster", ['player_id' => $fixture->player()->id])
        ->assertForbidden();

    actingAs($fixture->cast->member)
        ->delete("{$fixture->url}/teams/{$registration->id}")
        ->assertForbidden();
});

it('treats a registration from another season as missing', function (): void {
    $fixture = SquadFixture::make();
    $other = SquadFixture::make();
    $elsewhere = $other->register($other->club());

    actingAs($fixture->cast->admin)
        ->delete("{$fixture->url}/teams/{$elsewhere->id}")
        ->assertNotFound();

    expect(SeasonTeam::query()->whereKey($elsewhere->id)->exists())->toBeTrue();
});
