<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * The request was well formed and the caller was allowed to make it; the current state of
 * the world is what forbids it. Distinct from a validation failure, which is about the
 * values, and from a 403, which is about the caller.
 */
class ConflictException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'conflict')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    /**
     * Rendered back onto the form that caused it. A conflict is not attributable to one
     * input, so it goes in the same place a wrong email-and-password pair does.
     */
    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['conflict' => $this->getMessage()])->withInput();
    }
}
