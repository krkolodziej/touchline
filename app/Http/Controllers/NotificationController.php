<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The bell.
 *
 * Not scoped to an organization: a notification belongs to a person, and somebody who runs
 * three competitions wants one bell, not three. These are the only endpoints in the
 * application that answer JSON rather than a page, because the bell is a badge on a header
 * that every page already has — reloading the whole page to find out whether the number
 * changed would be absurd.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->with('organization')
            ->where('recipient_id', $this->caller($request)->id)
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(static fn (Notification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type->value,
                'title' => $notification->title,
                'body' => $notification->body,
                'link' => $notification->link,
                'organization_id' => $notification->organization_id,
                'organization_name' => $notification->organization->name,
                'created_at' => $notification->created_at->toAtomString(),
                'read_at' => $notification->read_at?->toAtomString(),
            ])
            ->values()
            ->all();

        return response()->json(['notifications' => $notifications]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => Notification::query()
                ->where('recipient_id', $this->caller($request)->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $marked = Notification::query()
            ->where('recipient_id', $this->caller($request)->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['marked' => $marked]);
    }

    /**
     * Somebody else's notification is a 404, not a 403. It is not yours, so as far as you are
     * concerned it does not exist.
     */
    public function markRead(Request $request, int $notificationId): RedirectResponse
    {
        $notification = Notification::query()
            ->where('recipient_id', $this->caller($request)->id)
            ->find($notificationId);

        if ($notification === null) {
            throw new NotFoundHttpException;
        }

        $notification->markRead();

        return redirect($notification->link);
    }

    private function caller(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new NotFoundHttpException;
        }

        return $user;
    }
}
