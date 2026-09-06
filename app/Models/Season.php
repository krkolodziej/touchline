<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $league_id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read League $league
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SeasonTeam> $seasonTeams
 */
class Season extends Model
{
    /** @use HasFactory<\Database\Factories\SeasonFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['league_id', 'name', 'start_date', 'end_date'];

    /** @return BelongsTo<League, $this> */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** @return HasMany<SeasonTeam, $this> */
    public function seasonTeams(): HasMany
    {
        return $this->hasMany(SeasonTeam::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }
}
