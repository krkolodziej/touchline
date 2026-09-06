<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * Delivering a notification once, however many times the job runs.
 *
 * A queue is at-least-once by nature: a worker that dies between finishing the work and
 * marking the job done will run it again, and it is right to. So "exactly once" is not a
 * property of the queue, it is a property of the write — three layers of it.
 *
 * The key describes the fact rather than the attempt. The unique index on it is the only
 * actual guarantee. And the pre-check below turns the common case — a redelivery — into one
 * SELECT that finds everything already there, rather than a fistful of failed inserts.
 */
class Notifier
{
    /**
     * @param  list<User>  $recipients
     * @return int how many were actually created
     */
    public function deliver(
        array $recipients,
        Organization $organization,
        NotificationType $type,
        string $subject,
        string $title,
        string $body,
        string $link,
    ): int {
        if ($recipients === []) {
            return 0;
        }

        $keys = [];

        foreach ($recipients as $recipient) {
            $keys[$recipient->id] = self::dedupeKey($type, $subject, $recipient);
        }

        $already = Notification::query()
            ->whereIn('dedupe_key', array_values($keys))
            ->pluck('dedupe_key')
            ->flip();

        $rows = [];
        $now = now();

        foreach ($recipients as $recipient) {
            if ($already->has($keys[$recipient->id])) {
                continue;
            }

            $rows[] = [
                'recipient_id' => $recipient->id,
                'organization_id' => $organization->id,
                'type' => $type->value,
                'title' => $title,
                'body' => $body,
                'link' => $link,
                'dedupe_key' => $keys[$recipient->id],
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        // One statement, so no transaction around it. Every nested transaction is a
        // savepoint, and a run that opens thousands of them makes PostgreSQL crawl.
        Notification::query()->insert($rows);

        return count($rows);
    }

    /**
     * "MATCH_FINISHED:41:7" — the type of thing that happened, the thing it happened to, and
     * who is being told. Nothing about when, or about which attempt this is.
     */
    public static function dedupeKey(NotificationType $type, string $subject, User $recipient): string
    {
        return sprintf('%s:%s:%d', $type->value, $subject, $recipient->id);
    }

    /**
     * Owners and administrators, never plain members.
     *
     * A notification that goes to everybody is a notification everybody learns to ignore, and
     * the people who need to know a match finished are the people who have to do something
     * about it.
     *
     * @return list<User>
     */
    public function managersOf(Organization $organization): array
    {
        /** @var list<User> $managers */
        $managers = OrganizationMembership::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->whereIn('role', ['OWNER', 'ADMIN'])
            ->get()
            ->map(static fn (OrganizationMembership $membership): User => $membership->user)
            ->values()
            ->all();

        return $managers;
    }
}
