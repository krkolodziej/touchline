<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\MatchStatus;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\Team;
use Database\Factories\LeagueFactory;
use Database\Factories\PlayerFactory;
use Database\Factories\SeasonFactory;
use Database\Factories\SeasonTeamFactory;
use Database\Factories\TeamFactory;

use function Pest\Laravel\actingAs;

/**
 * Two clubs of three, a season, and one match between them.
 *
 * The smallest world in which a result, a scorer list and a notification all mean something.
 * It lives here rather than in one test file because three of them want it.
 */
class Kickoff
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

    /** The season's address, for the tabs that hang off it. */
    public function seasonUrl(string $path = ''): string
    {
        return $this->cast->url("/leagues/{$this->season->league_id}/seasons/{$this->season->id}").$path;
    }

    /** @return list<Player> */
    public function squadOf(Team $team): array
    {
        return $this->squads[$team->id];
    }
}
