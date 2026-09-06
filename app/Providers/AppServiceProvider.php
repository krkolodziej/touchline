<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\SystemClock;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClockInterface::class, SystemClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
