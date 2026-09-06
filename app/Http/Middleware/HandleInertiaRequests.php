<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Props every page gets, whether it asked or not.
     *
     * `auth.user` is the whole session model on the client: a page never asks who is signed
     * in, it is told. Keep this list short — everything here travels with every response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user()?->only(['id', 'email', 'first_name', 'last_name']),
            ],

            // One-off messages after a redirect. Resolved lazily so a page that never
            // reads them does not pay for them.
            'flash' => [
                'message' => fn (): ?string => $request->session()->get('message'),
            ],
        ];
    }
}
