<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlayerPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One player, in one club's squad, for one season.
 *
 * @property int $id
 * @property int $season_team_id
 * @property int $player_id
 * @property int|null $shirt_number
 * @property PlayerPosition|null $position
 * @property bool $captain
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SeasonTeam $seasonTeam
 * @property-read Player $player
 */
class RosterEntry extends Model
{
    /** @use HasFactory<\Database\Factories\RosterEntryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['season_team_id', 'player_id', 'shirt_number', 'position', 'captain'];

    /** @return BelongsTo<SeasonTeam, $this> */
    public function seasonTeam(): BelongsTo
    {
        return $this->belongsTo(SeasonTeam::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => PlayerPosition::class, 'captain' => 'boolean'];
    }
}
