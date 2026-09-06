<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    public function __construct(
        Organization $resource,
        private readonly OrganizationRole $myRole,
        private readonly int $memberCount = 0,
    ) {
        parent::__construct($resource);
    }

    /**
     * Keys are snake_case and stay that way all the way to the input's `name`, so a
     * validation message comes back under the key the form already has.
     *
     * `created_at` keeps its full form because it really is an instant. Values that are
     * dates rather than instants — a season's start, a date of birth — are emitted as
     * `2026-08-15` from the stage that introduces them, so no client an hour west of here
     * renders the day before.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'my_role' => $this->myRole->value,
            'member_count' => $this->memberCount,
            'created_at' => $this->created_at->toAtomString(),
        ];
    }
}
