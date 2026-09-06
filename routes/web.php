<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\OrganizationController;
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

    // The dashboard *is* the list of organizations you belong to. A separate landing page
    // above it would be a click between somebody and the only thing they came for.
    Route::get('/dashboard', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    // Every id in the path is constrained to digits. A non-numeric segment is a 404 from
    // the router rather than a query with a cast in it.
    Route::prefix('/organizations/{organization}')
        ->whereNumber('organization')
        ->group(function (): void {
            Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');
            Route::patch('/', [OrganizationController::class, 'update'])->name('organizations.update');
            Route::delete('/', [OrganizationController::class, 'destroy'])->name('organizations.destroy');

            Route::post('/members', [MembershipController::class, 'store'])->name('members.store');
            Route::patch('/members/{membershipId}', [MembershipController::class, 'update'])
                ->whereNumber('membershipId')->name('members.update');
            Route::delete('/members/{membershipId}', [MembershipController::class, 'destroy'])
                ->whereNumber('membershipId')->name('members.destroy');
        });
});
