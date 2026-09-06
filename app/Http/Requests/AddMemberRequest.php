<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMemberRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],

            // OWNER is deliberately absent from the list: there is no endpoint anywhere
            // that mints a second owner.
            'role' => ['required', 'string', Rule::in(OrganizationRole::assignableValues())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Enter the email address of the person to add.',
            'email.email' => 'Enter a valid email address.',
            'role.in' => 'Choose a valid role.',
        ];
    }

    public function role(): OrganizationRole
    {
        return OrganizationRole::from($this->string('role')->value());
    }
}
