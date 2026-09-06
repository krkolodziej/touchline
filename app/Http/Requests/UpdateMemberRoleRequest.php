<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(OrganizationRole::assignableValues())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['role.in' => 'Choose a valid role.'];
    }

    public function role(): OrganizationRole
    {
        return OrganizationRole::from($this->string('role')->value());
    }
}
