<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Enums\MatchStatus;
use App\Enums\NotificationType;
use App\Models\Fixture;
use Psr\Clock\ClockInterface;

/**
 * "You have a match tomorrow."
 *
 * The window is deliberately as wide as the scan is frequent and no wider. The scan runs
 * every fifteen minutes and looks at matches kicking off between 23:45 and 24:15 from now,
 * so every match falls into exactly one run — wide enough that none is missed, narrow enough
 * that none is caught twice. The dedupe key would stop a second notification anyway; this
 * stops the work.
 */
class KickOffReminder
{
    private const HOURS_AHEAD = 24;

    private const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly Notifier $notifier,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @return array{matches: int, notifications: int}
     */
    public function run(): array
    {
        $now = $this->clock->now();
        $centre = $now->modify(sprintf('+%d hours', self::HOURS_AHEAD));

        $fixtures = Fixture::query()
            ->with(['homeTeam', 'awayTeam', 'season.league'])
            // Status, not just time. A match already being played needs no reminder, and a
            // postponed one keeps a stale kick-off that would otherwise fire every day.
            ->where('status', MatchStatus::Scheduled->value)
            ->whereNotNull('kick_off_at')
            ->where('kick_off_at', '>=', $centre->modify(sprintf('-%d minutes', self::WINDOW_MINUTES)))
            ->where('kick_off_at', '<', $centre->modify(sprintf('+%d minutes', self::WINDOW_MINUTES)))
            ->get();

        $sent = 0;

        foreach ($fixtures as $fixture) {
            $season = $fixture->season;
            $organization = $season->league->organization;

            $sent += $this->notifier->deliver(
                $this->notifier->managersOf($organization),
                $organization,
                NotificationType::KickOffReminder,
                (string) $fixture->id,
                sprintf('%s v %s tomorrow', $fixture->homeTeam->name, $fixture->awayTeam->name),
                sprintf(
                    'Round %d of %s %s, kicking off at %s.',
                    $fixture->round_number,
                    $season->league->name,
                    $season->name,
                    $fixture->kick_off_at?->format('H:i') ?? 'a time yet to be arranged',
                ),
                sprintf(
                    '/organizations/%d/leagues/%d/seasons/%d/fixtures/%d',
                    $organization->id,
                    $season->league_id,
                    $season->id,
                    $fixture->id,
                ),
            );
        }

        return ['matches' => $fixtures->count(), 'notifications' => $sent];
    }
}
