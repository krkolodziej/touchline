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

Built in nine stages, all of them in — see the [stage index](#stages). The one thing the
original has and this does not is live push over Mercure; the match page polls instead, which
[Deployment](#deployment) explains.

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

The worker is the one that is easy to forget, and nothing complains when it is missing:
results are recorded, the bell simply never fills, and the jobs wait in the `jobs` table for
somebody to notice.

### Background work

| | |
| --- | --- |
| What is queued | `php artisan queue:monitor database` |
| What failed | `php artisan queue:failed` |
| What is scheduled | `php artisan schedule:list` |
| Run the reminder scan now | `php artisan app:matches:remind` |

Finishing a match queues a notification for the organization's owners and administrators,
and the scan for matches kicking off in about a day runs every fifteen minutes. Both end up
in the same database as everything else: the queue is a table, so a job dispatched inside a
transaction is committed or rolled back with it.

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

## Deployment

`render.yaml` describes the whole thing, so a deploy is a blueprint pointed at this repository
rather than a list of settings somebody has to remember. The database is Neon, in the same
region as the instance.

One image serves both halves. The SPA is built in a Node stage and its hashed assets are
copied into a FrankenPHP runtime, where Caddy serves them off disk and everything it cannot
find falls through to Laravel. That is not tidiness: the session is a cookie, so one origin
means no CORS negotiation, no second service to keep awake, and no chance of the two halves
being deployed at different versions.

What has to be set by hand — everything else `render.yaml` either fills in or generates:

| | |
| --- | --- |
| `DB_URL` | The Neon connection string. The pooled one, since a free instance opens more connections than the direct endpoint likes |
| `APP_URL` | The address the instance ends up at, which is not known until it exists |

The rest is worth reading for what it says about the plan rather than the application.
Migrations run from the entrypoint rather than a pre-deploy hook, because hooks are a paid
feature; the queue worker and the scheduler run in the same container for the same reason.
The worker is bounded with `--max-time` and brought back by a loop, so a new deploy is picked
up without anybody restarting anything. An honest limitation follows: a free instance sleeps
after fifteen quiet minutes, and the worker sleeps with it, so a reminder due during a quiet
spell arrives when somebody next wakes the site.

The container runs as `www-data`, not as root. That is not caution for its own sake — running
as root is actively broken under a dropped-capability runtime, because what lets root ignore
file permissions is `CAP_DAC_OVERRIDE`, and without it root cannot write to `storage/`
either. CI starts the image with `--cap-drop=ALL` on every push for exactly that reason.

Config is deliberately never cached. A cached config freezes whatever the environment held
when it was built, and at build time that is a throwaway key and a database that does not
exist. Routes and views are cached, so the first request after a cold start is not the one
that pays for it.

### A way in without an account

With `DEMO_LOGIN_ENABLED=true` the sign-in page grows one more button, which signs the
visitor in as an administrator of the seeded league. An administrator can do everything worth
showing — start a match, record a goal, register a club — and cannot delete the organization,
which is the one thing reserved for its owner. With the switch off the route is not forbidden
but absent: a 404, and no button.

### What is not here

Kickoff pushes live match updates over Mercure. Touchline reloads the match page every three
seconds instead, through an Inertia partial reload that fetches only the fixture and its
events — and, since stage 8, one that usually answers `304 Not Modified` with no body at all,
because the response carries an ETag computed from its own bytes. That is the one deliberate
gap against the original.

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
| 7 | Queued notifications and scheduled reminders | ✅ |
| 8 | Demo access, conditional requests, deployment | ✅ |

---

## Licence

MIT.
