<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCacheHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            // Last, so it hashes the response everything else has finished with.
            SetCacheHeaders::class,
        ]);

        // The platform terminates TLS and forwards plain HTTP, so without this the
        // application builds http:// URLs behind an https:// address and declines to
        // set a secure session cookie — which looks exactly like sign-in not working.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo('/sign-in');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
