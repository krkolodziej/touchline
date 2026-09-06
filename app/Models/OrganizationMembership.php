<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's position in one organization. This row is the security boundary: every
 * scoped query joins through it, so there is no code path to a row outside it.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property OrganizationRole $role
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 */
class OrganizationMembership extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationMembershipFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['organization_id', 'user_id', 'role'];

    public function isOwner(): bool
    {
        return $this->role === OrganizationRole::Owner;
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => OrganizationRole::class];
    }
}
