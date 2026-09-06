<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One match: the appointment and the record of it.
 *
 * @property int $id
 * @property int $season_id
 * @property int $home_team_id
 * @property int $away_team_id
 * @property int $round_number
 * @property int $leg
 * @property Carbon|null $kick_off_at
 * @property MatchStatus $status
 * @property int $home_score
 * @property int $away_score
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Season $season
 * @property-read Team $homeTeam
 * @property-read Team $awayTeam
 */
class Fixture extends Model
{
    /** @use HasFactory<\Database\Factories\FixtureFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'season_id',
        'home_team_id',
        'away_team_id',
        'round_number',
        'leg',
        'kick_off_at',
        'status',
        'home_score',
        'away_score',
        'started_at',
        'finished_at',
    ];

    public function isLive(): bool
    {
        return $this->status === MatchStatus::Live;
    }

    public function involves(Team|int $team): bool
    {
        $id = $team instanceof Team ? $team->id : $team;

        return $this->home_team_id === $id || $this->away_team_id === $id;
    }

    /**
     * The only thing that moves the score, and it is called from exactly one place: the
     * recorder, inside the transaction that writes the goal.
     */
    public function recordGoalFor(Team|int $team): void
    {
        $id = $team instanceof Team ? $team->id : $team;

        if ($id === $this->home_team_id) {
            $this->home_score++;

            return;
        }

        $this->away_score++;
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'kick_off_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'round_number' => 'integer',
            'leg' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
        ];
    }
}
