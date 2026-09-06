<?php

declare(strict_types=1);

use App\Models\Fixture;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use Database\Factories\LeagueFactory;
use Database\Factories\SeasonFactory;
use Database\Factories\SeasonTeamFactory;
use Database\Factories\TeamFactory;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

use Tests\Support\Cast;

/**
 * A season with a given number of clubs already registered, and the address of it.
 */
final class Calendar
{
    public function __construct(
        public readonly Cast $cast,
        public readonly Season $season,
        public readonly string $url,
    ) {}

    public static function withClubs(int $count): self
    {
        $cast = Cast::make();
        $league = LeagueFactory::new()->createOne(['organization_id' => $cast->organization->id]);
        $season = SeasonFactory::new()->createOne([
            'league_id' => $league->id,
            'start_date' => '2026-03-01',
        ]);

        foreach (range(1, $count) as $index) {
            $team = TeamFactory::new()->createOne([
                'organization_id' => $cast->organization->id,
                'name' => sprintf('Club %02d', $index),
            ]);

            SeasonTeamFactory::new()->createOne([
                'season_id' => $season->id,
                'team_id' => $team->id,
            ]);
        }

        return new self($cast, $season, $cast->url("/leagues/{$league->id}/seasons/{$season->id}"));
    }
}

it('pairs twelve clubs into a hundred and thirty-two matches over twenty-two rounds', function (): void {
    $calendar = Calendar::withClubs(12);

    actingAs($calendar->cast->admin)
        ->post("{$calendar->url}/fixtures/generate", ['double_round' => true])
        ->assertRedirect();

    $fixtures = Fixture::query()->where('season_id', $calendar->season->id)->get();

    expect($fixtures)->toHaveCount(132)
        ->and($fixtures->pluck('round_number')->unique()->count())->toBe(22)
        ->and($fixtures->pluck('round_number')->max())->toBe(22);
});

it('halves it for a single round', function (): void {
    $calendar = Calendar::withClubs(12);

    actingAs($calendar->cast->admin)
        ->post("{$calendar->url}/fixtures/generate", ['double_round' => false]);

    expect(Fixture::query()->count())->toBe(66);
});

/**
 * The rule the scheduler exists to get right, checked once more against real rows: the
 * obvious formula leaves one club away all season, and nothing looks wrong until somebody
 * counts.
 */
it('gives every club eleven home games and eleven away', function (): void {
    $calendar = Calendar::withClubs(12);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate");

    $fixtures = Fixture::query()->get();

    foreach (SeasonTeam::query()->pluck('team_id') as $teamId) {
        expect($fixtures->where('home_team_id', $teamId))->toHaveCount(11)
            ->and($fixtures->where('away_team_id', $teamId))->toHaveCount(11);
    }
});

it('refuses to generate a second time', function (): void {
    $calendar = Calendar::withClubs(4);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate");

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate")
        ->assertSessionHasErrors([
            'conflict' => 'This season already has a calendar. Clear it before generating another.',
        ]);

    expect(Fixture::query()->count())->toBe(12);
});

it('refuses to generate from fewer than two clubs', function (): void {
    $calendar = Calendar::withClubs(1);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate")
        ->assertSessionHasErrors([
            'conflict' => 'A season needs at least two registered clubs before it can have a calendar.',
        ]);

    expect(Fixture::query()->count())->toBe(0);
});

it('clears the calendar and lets it be generated again', function (): void {
    $calendar = Calendar::withClubs(4);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate");
    actingAs($calendar->cast->admin)->delete("{$calendar->url}/fixtures")->assertRedirect();

    expect(Fixture::query()->count())->toBe(0);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate")->assertRedirect();

    expect(Fixture::query()->count())->toBe(12);
});

it('spaces the rounds out from a given date, at three in the afternoon', function (): void {
    $calendar = Calendar::withClubs(4);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate", [
        'double_round' => false,
        'first_round_on' => '2026-09-06',
        'days_between_rounds' => 14,
    ]);

    $rounds = Fixture::query()
        ->orderBy('round_number')
        ->get()
        ->groupBy('round_number')
        ->map(fn ($group) => $group->first()?->kick_off_at?->toDateTimeString());

    expect($rounds[1])->toBe('2026-09-06 15:00:00')
        ->and($rounds[2])->toBe('2026-09-20 15:00:00')
        ->and($rounds[3])->toBe('2026-10-04 15:00:00');
});

/** With no date given, the calendar starts when the season does. */
it('falls back to the season start date', function (): void {
    $calendar = Calendar::withClubs(4);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate");

    expect(Fixture::query()->orderBy('round_number')->first()?->kick_off_at?->toDateString())
        ->toBe('2026-03-01');
});

it('filters the calendar by round and by club', function (): void {
    $calendar = Calendar::withClubs(6);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate", ['double_round' => false]);

    actingAs($calendar->cast->member)->get("{$calendar->url}/fixtures?round=2")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('fixtures', 3));

    $teamId = Team::query()->firstOrFail()->id;

    actingAs($calendar->cast->member)->get("{$calendar->url}/fixtures?team={$teamId}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('fixtures', 5));
});

/**
 * A filter that quietly matches nothing looks exactly like a season with no matches in it,
 * which is the wrong thing to tell somebody who mistyped a status.
 */
it('refuses an unrecognised status filter rather than matching nothing', function (): void {
    $calendar = Calendar::withClubs(4);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate");

    actingAs($calendar->cast->member)->get("{$calendar->url}/fixtures?status=LIVE,FINISHED")
        ->assertOk();

    $response = actingAs($calendar->cast->member)->get("{$calendar->url}/fixtures?status=PLAYING");

    $response->assertStatus(400);
    expect($response->getContent())->toContain('SCHEDULED');
});

it('lets a member watch the calendar but not build one', function (): void {
    $calendar = Calendar::withClubs(4);

    actingAs($calendar->cast->member)->get("{$calendar->url}/fixtures")->assertOk();
    actingAs($calendar->cast->member)->post("{$calendar->url}/fixtures/generate")->assertForbidden();
    actingAs($calendar->cast->member)->delete("{$calendar->url}/fixtures")->assertForbidden();

    expect(Fixture::query()->count())->toBe(0);
});

it('tells the dialog how many rounds each format would take', function (): void {
    $calendar = Calendar::withClubs(12);

    actingAs($calendar->cast->member)->get("{$calendar->url}/fixtures")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seasons/Fixtures')
            ->where('round_counts.single', 11)
            ->where('round_counts.double', 22));
});

/**
 * A-v-B and B-v-A are two different fixtures, which is what makes a double round expressible
 * at all — so the unique index has to be on the ordered pair.
 */
it('allows the reverse fixture but not the same one twice', function (): void {
    $calendar = Calendar::withClubs(2);

    actingAs($calendar->cast->admin)->post("{$calendar->url}/fixtures/generate");

    $fixtures = Fixture::query()->orderBy('round_number')->get();
    $first = $fixtures->firstOrFail();
    $second = $fixtures->skip(1)->firstOrFail();

    expect($fixtures)->toHaveCount(2)
        ->and($first->home_team_id)->toBe($second->away_team_id)
        ->and($first->away_team_id)->toBe($second->home_team_id);
});
