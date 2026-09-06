<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Models\Fixture;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

use Tests\Support\Kickoff;

/**
 * `Kickoff` builds two clubs of three with one fixture between them. Everything here is
 * about which matches count, and for what.
 */
function scoreIt(Kickoff $match, int $home, int $away): void
{
    $match->start();

    foreach ([[$match->home, $home], [$match->away, $away]] as [$team, $goals]) {
        foreach (range(1, $goals) as $index) {
            if ($goals === 0) {
                break;
            }

            actingAs($match->cast->admin)->post("{$match->url}/events", [
                'type' => 'GOAL',
                'minute' => $index + ($team->id === $match->home->id ? 0 : 45),
                'team_id' => $team->id,
                'player_id' => $match->squadOf($team)[0]->id,
            ]);
        }
    }
}

it('gives three points to the winner and none to the loser', function (): void {
    $match = Kickoff::make();
    scoreIt($match, 2, 1);
    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->member)->get($match->seasonUrl().'/table')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seasons/Table')
            ->where('standings.0.team_name', 'Stal')
            ->where('standings.0.points', 3)
            ->where('standings.0.goal_difference', 1)
            ->where('standings.1.points', 0)
            ->where('standings.1.goal_difference', -1));
});

it('gives both clubs a point for a draw', function (): void {
    $match = Kickoff::make();
    scoreIt($match, 1, 1);
    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->member)->get($match->seasonUrl().'/table')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('standings.0.points', 1)
            ->where('standings.1.points', 1)
            ->where('standings.0.drawn', 1));
});

/**
 * The asymmetry the whole stage turns on. A goal is a goal the moment it is scored, so the
 * scorer list says so straight away — but three points are not awarded until full time,
 * because a match that is 2-1 at the hour is not a win. A table that reordered itself on a
 * Sunday afternoon and then reordered itself back would be worse than no table.
 */
it('counts a live match towards the scorers but not towards the table', function (): void {
    $match = Kickoff::make();
    scoreIt($match, 2, 0);

    $url = $match->seasonUrl();

    actingAs($match->cast->member)->get("{$url}/table")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('standings.0.played', 0)
            ->where('standings.0.points', 0)
            ->where('standings.0.goals_for', 0));

    actingAs($match->cast->member)->get("{$url}/statistics")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('players', 1)
            ->where('players.0.goals', 2));

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->member)->get("{$url}/table")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('standings.0.played', 1)
            ->where('standings.0.points', 3));
});

it('counts a cancelled match towards neither', function (): void {
    $match = Kickoff::make();
    scoreIt($match, 3, 0);
    actingAs($match->cast->admin)->post("{$match->url}/cancel");

    expect($match->fixture->fresh()?->status)->toBe(MatchStatus::Cancelled);

    $url = $match->seasonUrl();

    actingAs($match->cast->member)->get("{$url}/table")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('standings.0.played', 0));

    actingAs($match->cast->member)->get("{$url}/statistics")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('players', 0));
});

/** Every registered club, whether it has kicked a ball or not. */
it('lists every registered club in the table', function (): void {
    $match = Kickoff::make();

    actingAs($match->cast->member)->get($match->seasonUrl().'/table')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('standings', 2)
            ->where('standings.0.played', 0)
            ->where('standings.0.position', 1));
});

it('keeps cards out of the goal column and counts them separately', function (): void {
    $match = Kickoff::make()->start();
    $squad = $match->squadOf($match->home);

    foreach ([['GOAL', 10], ['YELLOW_CARD', 20], ['YELLOW_CARD', 30], ['RED_CARD', 40]] as [$type, $minute]) {
        actingAs($match->cast->admin)->post("{$match->url}/events", [
            'type' => $type,
            'minute' => $minute,
            'team_id' => $match->home->id,
            'player_id' => $squad[0]->id,
        ]);
    }

    actingAs($match->cast->member)->get($match->seasonUrl().'/statistics')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('players.0.goals', 1)
            ->where('players.0.yellow_cards', 2)
            ->where('players.0.red_cards', 1));
});

it('leaves a player who has done nothing off the statistics', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/events", [
        'type' => 'GOAL',
        'minute' => 10,
        'team_id' => $match->home->id,
        'player_id' => $match->squadOf($match->home)[0]->id,
    ]);

    // Six players in the two squads; one of them has done something.
    actingAs($match->cast->member)->get($match->seasonUrl().'/statistics')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('players', 1));
});

it('puts the leading scorer first', function (): void {
    $match = Kickoff::make()->start();
    $squad = $match->squadOf($match->home);

    foreach ([[0, 3], [1, 1]] as [$index, $goals]) {
        foreach (range(1, $goals) as $goal) {
            actingAs($match->cast->admin)->post("{$match->url}/events", [
                'type' => 'GOAL',
                'minute' => $index * 10 + $goal,
                'team_id' => $match->home->id,
                'player_id' => $squad[$index]->id,
            ]);
        }
    }

    actingAs($match->cast->member)->get($match->seasonUrl().'/statistics')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('players.0.goals', 3)
            ->where('players.0.player_id', $squad[0]->id)
            ->where('players.1.goals', 1));
});

it('sums the season on the overview', function (): void {
    $match = Kickoff::make();
    scoreIt($match, 2, 1);
    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->member)->get($match->seasonUrl().'/overview')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seasons/Overview')
            ->where('summary.clubs', 2)
            ->where('summary.played', 1)
            ->where('summary.goals', 3)
            ->has('top_of_the_table', 2)
            ->has('leading_scorers', 2));
});

it('shows what is being played now on the overview', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->member)->get($match->seasonUrl().'/overview')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('live', 1)
            ->has('upcoming', 0));
});

it('sends a season to its overview', function (): void {
    $match = Kickoff::make();
    $url = $match->seasonUrl();

    actingAs($match->cast->member)->get($url)->assertRedirect($url.'/overview');
});

it('builds a club profile from the same table the season renders', function (): void {
    $match = Kickoff::make();
    scoreIt($match, 2, 1);
    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->member)
        ->get($match->cast->url("/clubs/{$match->home->id}/profile"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('clubs/Show')
            ->where('club.name', 'Stal')
            ->has('seasons', 1)
            ->where('seasons.0.points', 3)
            ->where('seasons.0.position', 1)
            ->where('seasons.0.squad_size', 3)
            ->has('squad', 3));
});

it('builds a player career whose totals match the rows under them', function (): void {
    $match = Kickoff::make()->start();
    $scorer = $match->squadOf($match->home)[0];

    foreach ([10, 20, 30] as $minute) {
        actingAs($match->cast->admin)->post("{$match->url}/events", [
            'type' => 'GOAL',
            'minute' => $minute,
            'team_id' => $match->home->id,
            'player_id' => $scorer->id,
        ]);
    }

    actingAs($match->cast->member)
        ->get($match->cast->url("/players/{$scorer->id}/profile"))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('players/Show')
            ->where('totals.goals', 3)
            ->has('seasons', 1)
            ->where('seasons.0.goals', 3)
            ->where('seasons.0.team_name', 'Stal')
            ->where('current.team_name', 'Stal'));
});

it('cannot read a profile in another organization', function (): void {
    $mine = Kickoff::make();
    $theirs = Kickoff::make();

    actingAs($mine->cast->admin)
        ->get($mine->cast->url("/clubs/{$theirs->home->id}/profile"))
        ->assertNotFound();

    actingAs($mine->cast->admin)
        ->get($mine->cast->url("/players/{$theirs->squadOf($theirs->home)[0]->id}/profile"))
        ->assertNotFound();
});

it('keeps a stranger out of the table and the statistics', function (): void {
    $match = Kickoff::make();
    $url = $match->seasonUrl();

    actingAs($match->cast->stranger)->get("{$url}/table")->assertNotFound();
    actingAs($match->cast->stranger)->get("{$url}/statistics")->assertNotFound();
    actingAs($match->cast->stranger)->get("{$url}/overview")->assertNotFound();
});

/**
 * The table is computed, never stored, so it cannot fall out of step with the results — but
 * the arithmetic is worth reconciling against the fixtures once, in one place.
 */
it('reconciles the table against the matches it was built from', function (): void {
    $match = Kickoff::make();
    scoreIt($match, 3, 2);
    actingAs($match->cast->admin)->post("{$match->url}/finish");

    $response = actingAs($match->cast->member)->get($match->seasonUrl().'/table');
    /** @var array<string, mixed> $page */
    $page = $response->viewData('page');
    /** @var list<array<string, mixed>> $standings */
    $standings = $page['props']['standings'];

    $goalsInFixtures = (int) Fixture::query()
        ->where('status', MatchStatus::Finished->value)
        ->selectRaw('SUM(home_score + away_score) as total')
        ->value('total');

    expect(array_sum(array_column($standings, 'goals_for')))->toBe($goalsInFixtures)
        ->and(array_sum(array_column($standings, 'goals_against')))->toBe($goalsInFixtures)
        ->and(array_sum(array_column($standings, 'played')))->toBe(2)
        ->and(array_sum(array_column($standings, 'goal_difference')))->toBe(0);
});
