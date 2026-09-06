<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/SignUp');
    }

    /**
     * Creating an account signs it in. Asking somebody to type the password they have just
     * chosen twice, and then once more on a sign-in screen, is three times for one fact.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'email' => $request->string('email')->value(),
            'password' => $request->string('password')->value(),
            'first_name' => $request->string('first_name')->value(),
            'last_name' => $request->string('last_name')->value(),
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
