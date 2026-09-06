<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrganizationMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationMembership
 */
class MembershipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'email' => $this->user->email,
            'full_name' => $this->user->full_name,
            'role' => $this->role->value,
            'created_at' => $this->created_at->toAtomString(),
        ];
    }
}
