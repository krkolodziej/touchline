<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A club's registration for one season.
 *
 * @property int $id
 * @property int $season_id
 * @property int $team_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Season $season
 * @property-read Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RosterEntry> $rosterEntries
 */
class SeasonTeam extends Model
{
    /** @use HasFactory<\Database\Factories\SeasonTeamFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['season_id', 'team_id'];

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<RosterEntry, $this> */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }
}
