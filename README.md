# Touchline

Run an amateur football league: register clubs and squads, generate a season's calendar,
record what happens minute by minute in each match, and read the table and player statistics
straight off those events.

Nothing a reader sees is typed in twice. A goal is an event on a match; the score, the
league table and the top-scorer list are all derived from those events, so they cannot
disagree with one another.

**Laravel 12** + **Inertia** + **React 19**, one application rather than an API and a client
that have to be kept in step.

---

## Status

Built in stages. See the [stage index](#stages) below for what is in and what is next.

---

## Stack

| Layer | Choice | Why |
| --- | --- | --- |
| Application | Laravel 12, PHP 8.2 | Hand-written controllers and form requests — no resource scaffolding, so the framework's own mechanisms stay visible |
| Persistence | Eloquent, PostgreSQL 17 | Migrations are checked in and the test database is built by running them |
| Pages | Inertia + React 19, TypeScript, Vite | One round trip, one origin, no second contract to keep in step |
| Styling | Tailwind CSS v4, light and dark | Tokens in `@theme`, no `tailwind.config.js` — v4 does not have one |
| Tests | Pest, Vitest | |
| Static analysis | Larastan, level 8 · Pint | |

---

## Requirements

- PHP **8.2+** with `pdo_pgsql`, `intl`, `openssl`, `zip`
- Composer 2
- Node 22+
- PostgreSQL 14+

### If you are on XAMPP for Windows

Two extensions ship disabled and both are needed — `zip`, because Composer otherwise has to
clone every package from source, and `pdo_pgsql` to reach the database at all. Uncomment
them in `C:\xampp\php\php.ini`:

```ini
extension=pdo_pgsql
extension=zip
```

Confirm with `php -m | findstr /i "zip pgsql"`.

XAMPP ships no PostgreSQL server, so install one separately:

```bash
winget install -e --id PostgreSQL.PostgreSQL.17
```

---

## Setup

```bash
git clone https://github.com/krkolodziej/touchline.git
cd touchline
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Then point `.env` at your database and create the schema:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=touchline
DB_USERNAME=postgres
DB_PASSWORD=
```

```bash
php artisan migrate
```

The suite runs against a second database, `touchline_test`, and builds it by running the
real migrations — so a migration that no longer applies fails the suite rather than a
deployment. Create it once:

```bash
createdb -U postgres touchline_test
```

---

## Development

```bash
composer dev
```

That starts the application, a queue worker and Vite together. Separately, they are
`php artisan serve`, `php artisan queue:listen` and `npm run dev`.

### Checks

```bash
composer test
composer stan
composer cs
```

### A league to look at

```bash
php artisan app:seed:demo
```

Twelve clubs, full squads, a generated calendar and thirteen of twenty-two rounds already
played — including one match still in progress, one cancelled and two postponed, so every
state on every screen has something behind it. Every player has a date of birth and a name
of their own, and the matches carry cards and substitutions as well as goals, so no column
anywhere is a row of dashes. The command prints the account it made and a password to sign
in with.

It is deterministic: the same seed produces the same league on every machine, which is what
makes the table checkable against the results. It is also idempotent — run it twice and the
second run does nothing. Pass `--flush` to build it again from scratch.

Three seeded engines rather than one, and the split is the point: results, biography and
cards each draw from their own. Mt19937 is a sequence, so a new draw anywhere shifts every
draw after it — widening a list of first names would otherwise silently change every score
in the league.

Nothing is written straight into the score columns. Every match is started, its goals
recorded one at a time and then finished, through the same services the application uses, so
the demonstration exercises the rules rather than going around them.

```bash
npm run test
npm run typecheck
npm run build
```

---

## Stages

| | | |
| --- | --- | --- |
| 0 | Scaffold: Laravel, Inertia, the design system, the checks and CI | ✅ |
| 1 | Foundation: accounts, sessions, the shell | ✅ |
| 2 | Organizations and per-organization roles | ✅ |
| 3a | Leagues, clubs, players, and the list machinery | ✅ |
| 3b | Seasons, squad registration, rosters | ✅ |
| 4 | Round-robin fixture generation | ✅ |
| 5 | Matches, the state machine, goals and cards | ✅ |
| 6 | Standings, player statistics, demo data | ✅ |
| 7 | Queued notifications and scheduled reminders | |
| 8 | Realtime match updates, hardening, deployment | |

---

## Licence

MIT.
