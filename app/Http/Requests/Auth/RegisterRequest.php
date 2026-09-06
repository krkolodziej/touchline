<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Field names are the wire names, so a message comes back under the key the input
     * already has and the form needs no lookup table to place it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:180', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:4096', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'password.required' => 'Choose a password.',
            'password.min' => 'Use at least :min characters.',
            'password.confirmed' => 'The two passwords do not match.',
            'password_confirmation.required' => 'Repeat the password.',
        ];
    }

    /**
     * The unique rule is checked against the stored form of the address, so it has to see
     * the same normalisation the model applies. Without this, "Ada@example.com" passes a
     * uniqueness check that "ada@example.com" would have failed, and the insert then dies
     * on the index instead.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->value()))]);
        }
    }
}
