<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An ETag on reads, so a client that already has the answer is told so rather than sent it
 * again.
 *
 * That matters more here than it looks. The live match page reloads itself every three
 * seconds, and most of those reloads find nothing has changed — a match with no goal in the
 * last minute sends back exactly the bytes it sent last time. With an ETag those become 304s
 * with no body at all.
 *
 * Computed from the response bytes rather than from a stored timestamp. That is weaker than a
 * proper cache key — the page is still rendered before we find out it was not needed — but it
 * needs no bookkeeping anywhere, and cannot go stale, which a hand-maintained key eventually
 * does.
 *
 * Marked private, never public: every one of these responses is somebody's own view of their
 * own organization, and a shared cache holding one would hand it to the next person. Two
 * sessions cannot collide on a tag either, because the content itself carries session-specific
 * data — a full page carries the CSRF token, and an Inertia response carries who is signed in.
 */
class SetCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false) {
            return $response;
        }

        $response->setEtag(hash('xxh3', $content));
        $response->setPrivate();
        $response->isNotModified($request);

        return $response;
    }
}
