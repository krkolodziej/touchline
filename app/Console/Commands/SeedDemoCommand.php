<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Fixture\FixtureGenerator;
use App\Domain\Match\MatchEventRecorder;
use App\Domain\Match\MatchLifecycle;
use App\Domain\Organization\OrganizationManager;
use App\Enums\MatchEventType;
use App\Enums\OrganizationRole;
use App\Enums\PlayerPosition;
use App\Models\Fixture;
use App\Models\League;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A league worth looking at.
 *
 * Twelve clubs, full squads, a generated calendar and thirteen of twenty-two rounds already
 * played — including one match still in progress, one cancelled and two postponed, so every
 * state on every screen has something behind it. Every player has a date of birth and a name
 * of their own, and the matches carry cards and substitutions as well as goals, so no column
 * anywhere is a row of dashes and no feature is demonstrated by data that never exercises it.
 *
 * Nothing is written straight into the score columns. Every match is started, its goals
 * recorded one at a time and then finished, through the same services the application uses —
 * so the demonstration exercises the rules rather than going around them, and a rule that
 * breaks breaks here first.
 */
class SeedDemoCommand extends Command
{
    public const OWNER_EMAIL = 'demo@touchline.test';

    public const VISITOR_EMAIL = 'visitor@touchline.test';

    public const SLUG = 'demo';

    /**
     * One number, and the whole league follows from it. The same seed produces the same
     * results on every machine, which is what makes the table checkable against them.
     */
    private const SEED = 20260906;

    private const ROUNDS_PLAYED = 13;

    /** @var list<array{string, string}> */
    private const CLUBS = [
        ['Stal Rzeszów', 'Stal'],
        ['Resovia', 'Resovia'],
        ['Siarka Tarnobrzeg', 'Siarka'],
        ['Karpaty Krosno', 'Karpaty'],
        ['Sokół Sieniawa', 'Sokół'],
        ['Wisłok Wiśniowa', 'Wisłok'],
        ['Polonia Przemyśl', 'Polonia'],
        ['Czarni Jasło', 'Czarni'],
        ['Głogovia Głogów', 'Głogovia'],
        ['Piast Tuczempy', 'Piast'],
        ['Igloopol Dębica', 'Igloopol'],
        ['Błękitni Ropczyce', 'Błękitni'],
    ];

    /** @var list<string> */
    private const FIRST_NAMES = [
        'Jakub', 'Kacper', 'Antoni', 'Filip', 'Jan', 'Szymon', 'Franciszek', 'Michał',
        'Wojciech', 'Marcel', 'Mikołaj', 'Adam', 'Piotr', 'Tomasz', 'Paweł', 'Krzysztof',
        'Marcin', 'Grzegorz', 'Rafał', 'Łukasz', 'Bartosz', 'Damian', 'Sebastian', 'Dawid',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Nowak', 'Kowalski', 'Wiśniewski', 'Wójcik', 'Kowalczyk', 'Kamiński', 'Lewandowski',
        'Zieliński', 'Szymański', 'Woźniak', 'Dąbrowski', 'Kozłowski', 'Jankowski', 'Mazur',
        'Kwiatkowski', 'Krawczyk', 'Piotrowski', 'Grabowski', 'Nowakowski', 'Pawłowski',
        'Michalski', 'Nowicki', 'Adamczyk', 'Dudek', 'Zając', 'Wieczorek', 'Jabłoński',
        'Król', 'Majewski', 'Olszewski', 'Jaworski', 'Wróbel', 'Malinowski', 'Pawlak',
        'Witkowski', 'Walczak', 'Stępień', 'Górski', 'Rutkowski', 'Michalak',
    ];

    protected $signature = 'app:seed:demo
        {--flush : Delete the demonstration organization and build it again}
        {--owner-email= : The account that owns it}
        {--owner-password= : Its password; one is generated and printed if omitted}';

    protected $description = 'Build a league worth looking at: twelve clubs, full squads and thirteen rounds played';

    /**
     * Three engines, not one, and the split is the whole trick.
     *
     * Mt19937 is a sequence: a new draw anywhere shifts every draw after it. With one engine,
     * adding a first name to the list two years from now would silently change every score in
     * the league. Results, biography and colour each draw from their own stream, so a change
     * to one leaves the other two exactly where they were.
     */
    private Randomizer $random;

    private Randomizer $people;

    private Randomizer $flavour;

    public function handle(
        OrganizationManager $organizations,
        FixtureGenerator $fixtures,
        MatchLifecycle $lifecycle,
        MatchEventRecorder $recorder,
    ): int {
        $this->random = new Randomizer(new Mt19937(self::SEED));
        $this->people = new Randomizer(new Mt19937(self::SEED + 1));
        $this->flavour = new Randomizer(new Mt19937(self::SEED + 2));

        $existing = Organization::query()->where('slug', self::SLUG)->first();

        if ($existing !== null && ! $this->option('flush')) {
            // Idempotent on purpose: this runs on every container start in production, and
            // a second run has to be a no-op rather than a second league.
            $this->components->info('The demonstration league is already there. Pass --flush to build it again.');

            return self::SUCCESS;
        }

        if ($existing !== null) {
            $this->components->task('Removing the old one', function () use ($organizations, $existing): bool {
                $organizations->delete($existing);

                return true;
            });
        }

        $password = $this->ownerPassword();
        $owner = $this->account($this->ownerEmail(), $password, 'Demo', 'Owner');

        $organization = $this->organization($organizations, $owner);
        $league = League::query()->create([
            'organization_id' => $organization->id,
            'name' => 'District League',
            'slug' => 'district-league',
            'description' => 'Twelve clubs, home and away, from March to November.',
        ]);

        $season = Season::query()->create([
            'league_id' => $league->id,
            'name' => '2026',
            'start_date' => '2026-03-01',
            'end_date' => '2026-11-30',
        ]);

        $squads = $this->clubsAndSquads($organization, $season);

        $this->components->task('Generating the calendar', function () use ($fixtures, $season): bool {
            $fixtures->generate($season, doubleRound: true, firstRoundOn: null, daysBetweenRounds: 7);

            return true;
        });

        $this->playRounds($season, $squads, $lifecycle, $recorder);

        $this->newLine();
        $this->components->info(sprintf('Sign in as %s with the password %s', $this->ownerEmail(), $password));
        $this->components->info(sprintf('Season: %s %s (%d clubs, %d of %d rounds played)',
            $league->name,
            $season->name,
            count(self::CLUBS),
            self::ROUNDS_PLAYED,
            22,
        ));

        return self::SUCCESS;
    }

    private function organization(OrganizationManager $organizations, User $owner): Organization
    {
        $membership = $organizations->create($owner, 'Touchline Demo', self::SLUG);
        $organization = $membership->organization;

        // A second account, an administrator rather than the owner, and that is the point.
        // Everything worth demonstrating is open to an administrator — creating leagues,
        // registering clubs, running matches — while deleting the organization needs OWNER.
        // So a visitor let in without a password cannot destroy the thing they came to see.
        $visitor = $this->account(self::VISITOR_EMAIL, Str::random(32), 'Demo', 'Visitor');

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $visitor->id,
            'role' => OrganizationRole::Admin,
        ]);

        return $organization;
    }

    private function account(string $email, string $password, string $first, string $last): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        return User::query()->create([
            'email' => $email,
            'password' => $password,
            'first_name' => $first,
            'last_name' => $last,
        ]);
    }

    /**
     * Twelve clubs, each with sixteen to eighteen players, each player with a name nobody
     * else has and a date of birth that suits the shirt they wear.
     *
     * @return array<int, list<Player>> squads keyed by team id, in shirt order
     */
    private function clubsAndSquads(Organization $organization, Season $season): array
    {
        $names = $this->namePool();
        $squads = [];

        $this->components->task('Registering twelve clubs and their squads', function () use (
            $organization, $season, &$names, &$squads
        ): bool {
            foreach (self::CLUBS as [$name, $short]) {
                $team = Team::query()->create([
                    'organization_id' => $organization->id,
                    'name' => $name,
                    'slug' => Str::slug($name, '-', 'pl'),
                    'short_name' => $short,
                ]);

                $registration = SeasonTeam::query()->create([
                    'season_id' => $season->id,
                    'team_id' => $team->id,
                ]);

                $size = $this->random->getInt(16, 18);
                $captainNumber = $this->people->getInt(2, min(11, $size));
                $squad = [];

                foreach (range(1, $size) as $number) {
                    $position = $this->positionFor($number);
                    [$first, $last] = array_pop($names) ?? throw new LogicException(
                        'The name pool ran dry. Widen it rather than letting two players share a name.',
                    );

                    $player = Player::query()->create([
                        'organization_id' => $organization->id,
                        'first_name' => $first,
                        'last_name' => $last,
                        'date_of_birth' => $this->dateOfBirthFor($position, $season),
                    ]);

                    RosterEntry::query()->create([
                        'season_team_id' => $registration->id,
                        'player_id' => $player->id,
                        'shirt_number' => $number,
                        'position' => $position,
                        'captain' => $number === $captainNumber,
                    ]);

                    $squad[] = $player;
                }

                $squads[$team->id] = $squad;
            }

            return true;
        });

        return $squads;
    }

    /**
     * Every first name against every last name, shuffled once and then popped without
     * replacement — so no two players in the league share a name, and running out is a
     * crash rather than a quiet repetition.
     *
     * @return list<array{string, string}>
     */
    private function namePool(): array
    {
        $pool = [];

        foreach (self::FIRST_NAMES as $first) {
            foreach (self::LAST_NAMES as $last) {
                $pool[] = [$first, $last];
            }
        }

        /** @var list<array{string, string}> $shuffled */
        $shuffled = $this->people->shuffleArray($pool);

        return $shuffled;
    }

    private function positionFor(int $shirtNumber): PlayerPosition
    {
        return match (true) {
            $shirtNumber === 1 => PlayerPosition::Goalkeeper,
            $shirtNumber <= 6 => PlayerPosition::Defender,
            $shirtNumber <= 12 => PlayerPosition::Midfielder,
            default => PlayerPosition::Forward,
        };
    }

    private function dateOfBirthFor(PlayerPosition $position, Season $season): Carbon
    {
        [$youngest, $oldest] = match ($position) {
            PlayerPosition::Goalkeeper => [24, 38],
            PlayerPosition::Defender => [19, 35],
            PlayerPosition::Midfielder => [18, 34],
            PlayerPosition::Forward => [17, 32],
        };

        return Carbon::parse($season->start_date)
            ->subYears($this->people->getInt($youngest, $oldest))
            ->subDays($this->people->getInt(0, 364));
    }

    /**
     * Thirteen rounds, played through the real services.
     *
     * The four odd states are picked by position rather than at random, so anybody reading
     * this knows where to look for them: the third match played is cancelled, the sixth and
     * tenth are postponed, and the last one is left in progress.
     *
     * @param  array<int, list<Player>>  $squads
     */
    private function playRounds(
        Season $season,
        array $squads,
        MatchLifecycle $lifecycle,
        MatchEventRecorder $recorder,
    ): void {
        $played = Fixture::query()
            ->where('season_id', $season->id)
            ->where('round_number', '<=', self::ROUNDS_PLAYED)
            ->orderBy('round_number')
            ->orderBy('id')
            ->get()
            ->all();

        $last = count($played) - 1;
        $index = -1;

        $this->withProgressBar($played, function (Fixture $fixture) use (
            $squads, $lifecycle, $recorder, $last, &$index
        ): void {
            $index++;

            if ($index === 2) {
                $lifecycle->cancel($fixture);

                return;
            }

            if ($index === 5 || $index === 9) {
                $lifecycle->postpone($fixture);

                return;
            }

            $lifecycle->start($fixture);
            $this->playOut($fixture, $squads, $recorder);

            // One match left running, so the live screen has something live on it.
            if ($index !== $last) {
                $lifecycle->finish($fixture);
            }
        });

        $this->newLine(2);
    }

    /**
     * @param  array<int, list<Player>>  $squads
     */
    private function playOut(Fixture $fixture, array $squads, MatchEventRecorder $recorder): void
    {
        $minutes = [];

        // Home advantage, expressed as a slightly fatter distribution rather than as a bonus
        // goal: the home side scores a little more often, which is what it does.
        foreach ([[$fixture->home_team_id, 0.55], [$fixture->away_team_id, 0.45]] as [$teamId, $weight]) {
            $squad = $squads[$teamId] ?? [];

            if ($squad === []) {
                continue;
            }

            foreach (range(1, $this->goalCount($weight)) as $ignored) {
                $recorder->record(
                    $fixture,
                    MatchEventType::Goal,
                    $this->uniqueMinute($minutes, 1, $this->random),
                    $fixture->homeTeam->id === $teamId ? $fixture->homeTeam : $fixture->awayTeam,
                    $this->scorer($squad),
                );
            }

            // Roughly a third of sides pick up a booking, which is about right and means the
            // discipline column is never empty.
            if ($this->fraction($this->random) < 0.35) {
                $recorder->record(
                    $fixture,
                    MatchEventType::YellowCard,
                    $this->uniqueMinute($minutes, 1, $this->random),
                    $fixture->homeTeam->id === $teamId ? $fixture->homeTeam : $fixture->awayTeam,
                    $squad[$this->random->getInt(0, count($squad) - 1)],
                );
            }
        }

        $this->addColour($fixture, $squads, $recorder, $minutes);
    }

    /**
     * Reds and substitutions, drawn from the third engine and applied after the score is
     * settled — so widening this later cannot change a single result.
     *
     * @param  array<int, list<Player>>  $squads
     * @param  list<int>  $minutes
     */
    private function addColour(
        Fixture $fixture,
        array $squads,
        MatchEventRecorder $recorder,
        array $minutes,
    ): void {
        foreach ([$fixture->home_team_id, $fixture->away_team_id] as $teamId) {
            $squad = $squads[$teamId] ?? [];

            if (count($squad) < 13) {
                continue;
            }

            $team = $fixture->homeTeam->id === $teamId ? $fixture->homeTeam : $fixture->awayTeam;

            if ($this->fraction($this->flavour) < 0.06) {
                $recorder->record(
                    $fixture,
                    MatchEventType::RedCard,
                    $this->uniqueMinute($minutes, 20, $this->flavour),
                    $team,
                    $squad[$this->flavour->getInt(0, 10)],
                );
            }

            $bench = array_slice($squad, 11);

            foreach (range(1, $this->flavour->getInt(2, 3)) as $index) {
                if (! isset($bench[$index - 1])) {
                    break;
                }

                $recorder->record(
                    $fixture,
                    MatchEventType::Substitution,
                    $this->uniqueMinute($minutes, 55, $this->flavour),
                    $team,
                    $squad[$this->flavour->getInt(0, 10)],
                    $bench[$index - 1],
                );
            }
        }
    }

    /**
     * A fraction in [0, 1).
     *
     * `Randomizer::getFloat()` arrived in PHP 8.3 and this runs on 8.2, so it is one integer
     * draw divided down. That is one draw either way, which is what matters: the engines are
     * sequences, and a helper that spent two draws where the other spends one would put the
     * three streams permanently out of step.
     */
    private function fraction(Randomizer $engine): float
    {
        return $engine->getInt(0, 999_999) / 1_000_000;
    }

    private function goalCount(float $weight): int
    {
        $roll = $this->fraction($this->random) * $weight * 2;

        return match (true) {
            $roll < 0.30 => 0,
            $roll < 0.62 => 1,
            $roll < 0.84 => 2,
            $roll < 0.95 => 3,
            default => 4,
        };
    }

    /**
     * Goals come from the front half of the shirt list, which is where forwards live. A
     * league whose top scorer is a goalkeeper reads as broken even when the arithmetic is
     * right.
     *
     * @param  list<Player>  $squad
     */
    private function scorer(array $squad): Player
    {
        $from = min(6, count($squad) - 1);

        return $squad[$this->random->getInt($from, count($squad) - 1)];
    }

    /**
     * Two events in the same minute are legal and ordinary, but a match where three things
     * all happen in the 63rd reads as a bug. One minute each.
     *
     * @param  list<int>  $minutes
     */
    private function uniqueMinute(array &$minutes, int $from, Randomizer $engine): int
    {
        do {
            $minute = $engine->getInt($from, 90);
        } while (in_array($minute, $minutes, true));

        $minutes[] = $minute;

        return $minute;
    }

    private function ownerEmail(): string
    {
        $value = $this->option('owner-email');

        return is_string($value) && $value !== '' ? $value : self::OWNER_EMAIL;
    }

    private function ownerPassword(): string
    {
        $value = $this->option('owner-password');

        return is_string($value) && $value !== '' ? $value : bin2hex(random_bytes(8));
    }
}
