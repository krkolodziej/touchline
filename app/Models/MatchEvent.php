<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something that happened in a match.
 *
 * Append-only: there is no endpoint that edits or deletes one. The score, the table and the
 * scorer list are all derived from these rows, so an editable event is a score that can stop
 * matching its own history without anything noticing. A mistake is corrected by recording
 * the truth.
 *
 * @property int $id
 * @property int $fixture_id
 * @property MatchEventType $type
 * @property int $minute
 * @property int $team_id
 * @property int $player_id
 * @property int|null $related_player_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Fixture $fixture
 * @property-read Team $team
 * @property-read Player $player
 * @property-read Player|null $relatedPlayer
 */
class MatchEvent extends Model
{
    /** @use HasFactory<\Database\Factories\MatchEventFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['fixture_id', 'type', 'minute', 'team_id', 'player_id', 'related_player_id'];

    /** @return BelongsTo<Fixture, $this> */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function relatedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'related_player_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => MatchEventType::class, 'minute' => 'integer'];
    }
}
