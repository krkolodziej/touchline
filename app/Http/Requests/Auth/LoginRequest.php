<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Five wrong passwords a minute for one account from one address, and twenty-five from
     * that address whatever account it names. Two limiters rather than one because they
     * answer different questions: the first stops somebody guessing one person's password,
     * the second stops somebody working through a list of addresses.
     */
    private const MAX_ATTEMPTS = 5;

    private const ADDRESS_MULTIPLIER = 5;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
            'password.required' => 'Enter your password.',
        ];
    }

    /**
     * A wrong password and an address nobody has registered are answered identically. The
     * pair is what is wrong; saying which half would let anyone with a list of addresses
     * find out which of them have accounts here.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => mb_strtolower(trim($this->string('email')->value())),
            'password' => $this->string('password')->value(),
        ];

        if (! Auth::attempt($credentials)) {
            foreach ($this->limiterKeys() as $key => $limit) {
                RateLimiter::hit($key);
            }

            // Keyed under `credentials`, not `email`: it is the pair that is wrong, and
            // hanging the message off one input implies the other one was accepted.
            throw ValidationException::withMessages([
                'credentials' => 'Those credentials do not match anything we hold.',
            ]);
        }

        // A successful sign-in clears the counters. Otherwise somebody who mistyped their
        // password four times and then got it right stays four-fifths of the way to a
        // lockout for the rest of the minute.
        foreach ($this->limiterKeys() as $key => $limit) {
            RateLimiter::clear($key);
        }
    }

    /**
     * The one sign-in failure worth telling apart, and one that leaks nothing: it is true
     * whether or not the account exists.
     */
    private function ensureIsNotRateLimited(): void
    {
        foreach ($this->limiterKeys() as $key => $limit) {
            if (! RateLimiter::tooManyAttempts($key, $limit)) {
                continue;
            }

            Event::dispatch(new Lockout($this));

            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'credentials' => sprintf(
                    'Too many sign-in attempts. Try again in %d second%s.',
                    $seconds,
                    $seconds === 1 ? '' : 's',
                ),
            ]);
        }
    }

    /** @return array<string, int> */
    private function limiterKeys(): array
    {
        $address = $this->ip() ?? 'unknown';
        $email = Str::lower(trim($this->string('email')->value()));

        return [
            'sign-in:'.sha1($email.'|'.$address) => self::MAX_ATTEMPTS,
            'sign-in-from:'.sha1($address) => self::MAX_ATTEMPTS * self::ADDRESS_MULTIPLIER,
        ];
    }
}
