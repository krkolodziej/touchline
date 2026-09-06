<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A club. Deliberately not tied to a league: it is registered once and reused, season after
 * season, promoted or relegated or entered in two competitions at the same time.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string $short_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $display_short_name
 * @property-read Organization $organization
 */
class Team extends Model
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['organization_id', 'name', 'slug', 'short_name'];

    /**
     * Falls back to the full name, so a fixture list never has a blank on one side.
     *
     * @return Attribute<string, never>
     */
    protected function displayShortName(): Attribute
    {
        return Attribute::get(fn (): string => $this->short_name === '' ? $this->name : $this->short_name);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
