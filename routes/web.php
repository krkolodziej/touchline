<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/sign-in', [SessionController::class, 'create'])->name('sign-in');
    Route::post('/sign-in', [SessionController::class, 'store']);

    Route::get('/sign-up', [RegistrationController::class, 'create'])->name('sign-up');
    Route::post('/sign-up', [RegistrationController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/sign-out', [SessionController::class, 'destroy'])->name('sign-out');

    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
});
