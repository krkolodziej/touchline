<?php

declare(strict_types=1);

use App\Domain\Notification\KickOffReminder;
use App\Enums\NotificationType;
use App\Enums\OrganizationRole;
use App\Jobs\MatchFinished;
use App\Jobs\SendKickOffReminders;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

use RuntimeException;
use Tests\Support\Kickoff;

/**
 * `Kickoff` comes from MatchTest: two clubs of three, one fixture, and an admin who can run
 * it. The cast's admin and member are both in the organization, so "who gets told" is a real
 * question here rather than a hypothetical one.
 */
it('tells the people who run the competition when a match finishes', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    $notifications = Notification::query()->get();

    // The admin and the owner-less organization's admin: `Cast::make()` creates an admin and
    // a member, and only the admin is a manager.
    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()?->recipient_id)->toBe($match->cast->admin->id)
        ->and($notifications->first()?->type)->toBe(NotificationType::MatchFinished)
        ->and($notifications->first()?->title)->toContain('Stal')
        ->and($notifications->first()?->link)->toStartWith('/organizations/');
});

/**
 * A notification that goes to everybody is a notification everybody learns to ignore.
 */
it('does not tell plain members', function (): void {
    $match = Kickoff::make()->start();
    $owner = memberOf($match->cast->organization, OrganizationRole::Owner);

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    $recipients = Notification::query()->pluck('recipient_id')->all();

    expect($recipients)->toHaveCount(2)
        ->and($recipients)->toContain($match->cast->admin->id)
        ->and($recipients)->toContain($owner->id)
        ->and($recipients)->not->toContain($match->cast->member->id);
});

/**
 * The whole reason the queue lives in the same database as everything else.
 *
 * `dispatch()` on the database driver is an INSERT on this connection, so the job row is
 * committed with the status change or rolled back with it. This test reads the `jobs` table
 * directly rather than trusting the queue's own bookkeeping, because the claim is about the
 * table.
 *
 * The rest of the suite runs the queue synchronously, which is what makes the delivery tests
 * readable — but a synchronous queue never touches `jobs` at all, so these two say what they
 * need out loud.
 */
it('queues the job in the same transaction as the result', function (): void {
    config(['queue.default' => 'database']);

    $match = Kickoff::make()->start();

    expect(DB::table('jobs')->count())->toBe(0);

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    expect(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->value('payload'))->toContain('MatchFinished');
});

it('queues nothing when the transaction is rolled back', function (): void {
    config(['queue.default' => 'database']);

    $match = Kickoff::make()->start();
    $lifecycle = app(App\Domain\Match\MatchLifecycle::class);

    expect(function () use ($lifecycle, $match): void {
        DB::transaction(function () use ($lifecycle, $match): void {
            $lifecycle->finish($match->fixture);

            throw new RuntimeException('Something went wrong after the whistle.');
        });
    })->toThrow(RuntimeException::class);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and($match->fixture->fresh()?->status)->toBe(App\Enums\MatchStatus::Live);
});

/**
 * A queue is at-least-once by nature: a worker that dies between doing the work and marking
 * the job done will run it again, and it is right to. So the second run has to be a no-op.
 */
it('delivers once however many times the job runs', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    (new MatchFinished($match->fixture->id))->handle(app(App\Domain\Notification\Notifier::class));
    (new MatchFinished($match->fixture->id))->handle(app(App\Domain\Notification\Notifier::class));

    expect(Notification::query()->count())->toBe(1);
});

/**
 * The world is allowed to have moved on between the dispatch and the handling. None of these
 * is an error, and none is worth a failed job — there is simply nothing to announce.
 */
it('says nothing about a match that is no longer finished', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");
    Notification::query()->delete();

    // Reopened by hand, the way a correction would do it.
    $match->fixture->fresh()?->forceFill(['status' => 'LIVE'])->save();

    (new MatchFinished($match->fixture->id))->handle(app(App\Domain\Notification\Notifier::class));

    expect(Notification::query()->count())->toBe(0);
});

it('says nothing about a match that is gone', function (): void {
    $notifier = app(App\Domain\Notification\Notifier::class);

    (new MatchFinished(999_999))->handle($notifier);

    expect(Notification::query()->count())->toBe(0);
});

it('reminds whoever runs a match kicking off in about a day', function (): void {
    Carbon::setTestNow('2026-05-01 12:00:00');

    $match = Kickoff::make();
    $match->fixture->forceFill(['kick_off_at' => '2026-05-02 12:05:00'])->save();

    $result = app(KickOffReminder::class)->run();

    expect($result['matches'])->toBe(1)
        ->and($result['notifications'])->toBe(1);

    $notification = Notification::query()->sole();

    expect($notification->type)->toBe(NotificationType::KickOffReminder)
        ->and($notification->title)->toBe('Stal v Resovia tomorrow');

    Carbon::setTestNow();
});

/**
 * The window is as wide as the scan is frequent and no wider, so every match falls into
 * exactly one run.
 */
it('leaves alone anything outside the window', function (): void {
    Carbon::setTestNow('2026-05-01 12:00:00');

    $match = Kickoff::make();

    foreach (['2026-05-02 11:30:00', '2026-05-02 12:30:00', '2026-05-03 12:00:00'] as $kickOff) {
        $match->fixture->forceFill(['kick_off_at' => $kickOff])->save();

        expect(app(KickOffReminder::class)->run()['matches'])->toBe(0, $kickOff);
    }

    Carbon::setTestNow();
});

/**
 * Status, not just time. A match already being played needs no reminder, and a postponed one
 * keeps a stale kick-off that would otherwise fire every day it stays postponed.
 */
it('reminds nobody about a match that is not scheduled', function (): void {
    Carbon::setTestNow('2026-05-01 12:00:00');

    $match = Kickoff::make();
    $match->fixture->forceFill(['kick_off_at' => '2026-05-02 12:00:00'])->save();

    actingAs($match->cast->admin)->post("{$match->url}/postpone");

    expect(app(KickOffReminder::class)->run()['matches'])->toBe(0);

    Carbon::setTestNow();
});

it('reminds once, however often the scan runs', function (): void {
    Carbon::setTestNow('2026-05-01 12:00:00');

    $match = Kickoff::make();
    $match->fixture->forceFill(['kick_off_at' => '2026-05-02 12:00:00'])->save();

    app(KickOffReminder::class)->run();
    app(KickOffReminder::class)->run();

    expect(Notification::query()->count())->toBe(1);

    Carbon::setTestNow();
});

it('can be run by hand from the console', function (): void {
    expect(Artisan::call('app:matches:remind'))->toBe(0);
});

it('schedules the scan every fifteen minutes', function (): void {
    Queue::fake();

    $schedule = app(Illuminate\Console\Scheduling\Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($event): bool => $event->description === 'kick-off-reminders');

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('*/15 * * * *');

    expect(new SendKickOffReminders)->toBeInstanceOf(SendKickOffReminders::class);
});

it('counts and lists a person their own notifications', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->admin)->get('/notifications/unread-count')
        ->assertOk()
        ->assertJson(['count' => 1]);

    $response = actingAs($match->cast->admin)->get('/notifications')->assertOk();

    /** @var array{notifications: list<array<string, mixed>>} $body */
    $body = $response->json();

    expect($body['notifications'])->toHaveCount(1)
        ->and($body['notifications'][0]['read_at'])->toBeNull();

    // The member is in the same organization and has none of it.
    actingAs($match->cast->member)->get('/notifications/unread-count')
        ->assertOk()
        ->assertJson(['count' => 0]);
});

it('marks everything read at once', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    actingAs($match->cast->admin)->post('/notifications/read')
        ->assertOk()
        ->assertJson(['marked' => 1]);

    actingAs($match->cast->admin)->get('/notifications/unread-count')->assertJson(['count' => 0]);
});

/** Set once and never overwritten: a second sweep should not rewrite when somebody first saw it. */
it('does not move the moment something was first read', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    $notification = Notification::query()->sole();
    $notification->markRead();
    $first = $notification->fresh()?->read_at;

    Carbon::setTestNow(now()->addHour());
    $notification->fresh()?->markRead();

    expect($notification->fresh()?->read_at?->toDateTimeString())->toBe($first?->toDateTimeString());

    Carbon::setTestNow();
});

it('takes somebody to what they were told about, and marks it read', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    $notification = Notification::query()->sole();

    actingAs($match->cast->admin)->post("/notifications/{$notification->id}/read")
        ->assertRedirect($notification->link);

    expect($notification->fresh()?->read_at)->not->toBeNull();
});

/** Not yours, so as far as you are concerned it does not exist. */
it('treats somebody else\'s notification as missing', function (): void {
    $match = Kickoff::make()->start();

    actingAs($match->cast->admin)->post("{$match->url}/finish");

    $notification = Notification::query()->sole();

    actingAs($match->cast->member)->post("/notifications/{$notification->id}/read")
        ->assertNotFound();

    expect($notification->fresh()?->read_at)->toBeNull();
});

it('turns an anonymous visitor away from the bell', function (): void {
    get('/notifications')->assertRedirect('/sign-in');
    post('/notifications/read')->assertRedirect('/sign-in');
});
