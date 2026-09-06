<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notification\KickOffReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A tick. It carries nothing at all.
 *
 * The window is worked out when the job is *handled*, not when it is dispatched, so a worker
 * that is twenty minutes behind asks "what kicks off around now plus a day" rather than
 * "what kicked off around the moment somebody scheduled this".
 */
class SendKickOffReminders implements ShouldQueue
{
    use Queueable;

    public function handle(KickOffReminder $reminder): void
    {
        $reminder->run();
    }
}
