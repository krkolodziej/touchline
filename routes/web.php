<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\FixtureController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SeasonTableController;
use App\Http\Controllers\SquadController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/sign-in', [SessionController::class, 'create'])->name('sign-in');
    Route::post('/sign-in', [SessionController::class, 'store']);

    Route::get('/sign-up', [RegistrationController::class, 'create'])->name('sign-up');
    Route::post('/sign-up', [RegistrationController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/sign-out', [SessionController::class, 'destroy'])->name('sign-out');

    // A notification belongs to a person, not to an organization: somebody who runs
    // three competitions wants one bell, not three.
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead'])
        ->whereNumber('notificationId')->name('notifications.read');

    // The dashboard *is* the list of organizations you belong to. A separate landing page
    // above it would be a click between somebody and the only thing they came for.
    Route::get('/dashboard', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    // Every id in the path is constrained to digits. A non-numeric segment is a 404 from the
    // router rather than a query with a cast in it.
    Route::prefix('/organizations/{organization}')
        ->whereNumber('organization')
        ->group(function (): void {
            Route::patch('/', [OrganizationController::class, 'update'])->name('organizations.update');
            Route::delete('/', [OrganizationController::class, 'destroy'])->name('organizations.destroy');

            // An organization has no page of its own — it is four tabs, and the first of
            // them is what somebody came to see. A `Route::redirect` cannot serve this: the
            // route parameter is bound to a scope object, and interpolating one into a URL
            // template is not a thing. It also has to answer 404 to a stranger, which a
            // static redirect would not.
            Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');

            Route::get('/leagues', [LeagueController::class, 'index'])->name('organizations.leagues');
            Route::post('/leagues', [LeagueController::class, 'store'])->name('leagues.store');
            Route::patch('/leagues/{leagueId}', [LeagueController::class, 'update'])
                ->whereNumber('leagueId')->name('leagues.update');
            Route::delete('/leagues/{leagueId}', [LeagueController::class, 'destroy'])
                ->whereNumber('leagueId')->name('leagues.destroy');

            // Everything under one league. `{league}`, `{season}` and `{seasonTeam}` are
            // bound to scopes that each prove the level above them, so a season reached
            // through the wrong league is as absent as one that never existed.
            Route::prefix('/leagues/{league}')
                ->whereNumber('league')
                ->group(function (): void {
                    Route::get('/', [SeasonController::class, 'index'])->name('leagues.show');
                    Route::post('/seasons', [SeasonController::class, 'store'])->name('seasons.store');
                    Route::delete('/seasons/{seasonId}', [SeasonController::class, 'destroy'])
                        ->whereNumber('seasonId')->name('seasons.destroy');

                    Route::prefix('/seasons/{season}')
                        ->whereNumber('season')
                        ->group(function (): void {
                            Route::get('/', [SeasonController::class, 'show'])->name('seasons.show');

                            Route::get('/overview', [SeasonTableController::class, 'overview'])
                                ->name('seasons.overview');
                            Route::get('/table', [SeasonTableController::class, 'table'])
                                ->name('seasons.table');
                            Route::get('/statistics', [SeasonTableController::class, 'statistics'])
                                ->name('seasons.statistics');

                            Route::get('/squads', [SquadController::class, 'index'])->name('seasons.squads');

                            Route::get('/fixtures', [FixtureController::class, 'index'])
                                ->name('seasons.fixtures');
                            Route::post('/fixtures/generate', [FixtureController::class, 'generate'])
                                ->name('fixtures.generate');
                            Route::delete('/fixtures', [FixtureController::class, 'clear'])
                                ->name('fixtures.clear');

                            // One match. It gets its own address rather than a panel inside
                            // the calendar, because it is the thing somebody sends a link to
                            // while it is being played.
                            Route::prefix('/fixtures/{fixture}')
                                ->whereNumber('fixture')
                                ->group(function (): void {
                                    Route::get('/', [MatchController::class, 'show'])
                                        ->name('matches.show');

                                    Route::post('/events', [MatchController::class, 'recordEvent'])
                                        ->name('match-events.store');

                                    // One route for five verbs, because they are one machine.
                                    // Five endpoints would be five places to forget a check.
                                    // Declared last, and constrained, so it cannot swallow
                                    // the events route above it.
                                    Route::post('/{verb}', [MatchController::class, 'transition'])
                                        ->whereIn('verb', ['start', 'finish', 'cancel', 'postpone', 'reschedule'])
                                        ->name('matches.transition');
                                });

                            Route::post('/teams', [SquadController::class, 'register'])
                                ->name('season-teams.store');

                            Route::prefix('/teams/{seasonTeam}')
                                ->whereNumber('seasonTeam')
                                ->group(function (): void {
                                    Route::delete('/', [SquadController::class, 'withdraw'])
                                        ->name('season-teams.destroy');

                                    Route::post('/roster', [SquadController::class, 'addToSquad'])
                                        ->name('roster.store');
                                    Route::patch('/roster/{rosterEntryId}', [SquadController::class, 'updateEntry'])
                                        ->whereNumber('rosterEntryId')->name('roster.update');
                                    Route::delete('/roster/{rosterEntryId}', [SquadController::class, 'removeEntry'])
                                        ->whereNumber('rosterEntryId')->name('roster.destroy');
                                });
                        });
                });

            Route::get('/clubs', [TeamController::class, 'index'])->name('organizations.clubs');
            Route::post('/clubs', [TeamController::class, 'store'])->name('teams.store');
            Route::patch('/clubs/{teamId}', [TeamController::class, 'update'])
                ->whereNumber('teamId')->name('teams.update');
            Route::delete('/clubs/{teamId}', [TeamController::class, 'destroy'])
                ->whereNumber('teamId')->name('teams.destroy');

            Route::get('/players', [PlayerController::class, 'index'])->name('organizations.players');
            Route::post('/players', [PlayerController::class, 'store'])->name('players.store');
            Route::patch('/players/{playerId}', [PlayerController::class, 'update'])
                ->whereNumber('playerId')->name('players.update');
            Route::delete('/players/{playerId}', [PlayerController::class, 'destroy'])
                ->whereNumber('playerId')->name('players.destroy');

            // A club and a player outlive any one season, so their pages sit beside the
            // competitions rather than inside one.
            Route::get('/clubs/{teamId}/profile', [ProfileController::class, 'club'])
                ->whereNumber('teamId')->name('clubs.profile');
            Route::get('/players/{playerId}/profile', [ProfileController::class, 'player'])
                ->whereNumber('playerId')->name('players.profile');

            Route::get('/members', [MembershipController::class, 'index'])->name('organizations.members');
            Route::post('/members', [MembershipController::class, 'store'])->name('members.store');
            Route::patch('/members/{membershipId}', [MembershipController::class, 'update'])
                ->whereNumber('membershipId')->name('members.update');
            Route::delete('/members/{membershipId}', [MembershipController::class, 'destroy'])
                ->whereNumber('membershipId')->name('members.destroy');
        });
});
