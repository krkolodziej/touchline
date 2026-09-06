<?php

declare(strict_types=1);

use App\Domain\Notification\KickOffReminder;
use App\Jobs\SendKickOffReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Run the reminder scan by hand, which is also how the scheduled job gets its work done.
 * Useful for finding out why nothing arrived without waiting a quarter of an hour to see it
 * not arrive again.
 */
Artisan::command('app:matches:remind', function (KickOffReminder $reminder): void {
    $result = $reminder->run();

    $this->components->info(sprintf(
        '%d match%s in the window, %d notification%s sent.',
        $result['matches'],
        $result['matches'] === 1 ? '' : 'es',
        $result['notifications'],
        $result['notifications'] === 1 ? '' : 's',
    ));
})->purpose('Scan for matches kicking off in about a day and remind whoever runs them');

/**
 * The schedule is code, not a crontab entry on a machine somebody has to remember to set up.
 * It is checked in, it is reviewed with everything else, and a fresh checkout has it.
 *
 * Fifteen minutes because the reminder scan looks at a thirty-minute window centred a day
 * out — every match falls into exactly one run. `withoutOverlapping` because a slow scan
 * should make the next one wait rather than run beside it.
 */
Schedule::job(new SendKickOffReminders)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('kick-off-reminders');
