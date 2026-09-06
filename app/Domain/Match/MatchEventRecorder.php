<?php

declare(strict_types=1);

namespace App\Domain\Match;

use App\Enums\MatchEventType;
use App\Exceptions\ConflictException;
use App\Models\Fixture;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\SeasonTeam;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording what happened, and moving the score when it was a goal.
 *
 * The score is never typed in. It changes here and nowhere else, in the same transaction
 * that writes the goal — so the number on the scoreboard and the list of goals underneath it
 * cannot disagree, because there is no moment at which one exists without the other.
 */
class MatchEventRecorder
{
    public function record(
        Fixture $fixture,
        MatchEventType $type,
        int $minute,
        Team $team,
        Player $player,
        ?Player $relatedPlayer = null,
    ): MatchEvent {
        // Events are recorded while a match is being played, and only then. A goal against a
        // match that has not kicked off is not a goal, it is a mistake about which match.
        if (! $fixture->isLive()) {
            throw new ConflictException(
                sprintf(
                    'Events can only be recorded while a match is live. This one is %s.',
                    strtolower($fixture->status->value),
                ),
                'match_not_live',
            );
        }

        if (! $fixture->involves($team)) {
            throw ValidationException::withMessages([
                'team_id' => 'That club is not playing in this match.',
            ]);
        }

        if (! $this->isRostered($fixture, $team, $player)) {
            throw ValidationException::withMessages([
                'player_id' => "That player is not in this club's squad for this season.",
            ]);
        }

        if ($type->needsRelatedPlayer()) {
            $this->guardSubstitution($fixture, $team, $player, $relatedPlayer);
        } elseif ($relatedPlayer !== null) {
            throw ValidationException::withMessages([
                'related_player_id' => 'Only a substitution involves a second player.',
            ]);
        }

        return DB::transaction(function () use ($fixture, $type, $minute, $team, $player, $relatedPlayer): MatchEvent {
            $event = MatchEvent::query()->create([
                'fixture_id' => $fixture->id,
                'type' => $type,
                'minute' => $minute,
                'team_id' => $team->id,
                'player_id' => $player->id,
                'related_player_id' => $relatedPlayer?->id,
            ]);

            if ($type->movesTheScore()) {
                $fixture->recordGoalFor($team);
                $fixture->save();
            }

            return $event;
        });
    }

    private function guardSubstitution(
        Fixture $fixture,
        Team $team,
        Player $player,
        ?Player $relatedPlayer,
    ): void {
        if ($relatedPlayer === null) {
            throw ValidationException::withMessages([
                'related_player_id' => 'A substitution needs the player coming on.',
            ]);
        }

        if ($relatedPlayer->id === $player->id) {
            throw ValidationException::withMessages([
                'related_player_id' => 'A player cannot be substituted for themselves.',
            ]);
        }

        if (! $this->isRostered($fixture, $team, $relatedPlayer)) {
            throw ValidationException::withMessages([
                'related_player_id' => "The player coming on is not in this club's squad for this season.",
            ]);
        }
    }

    /**
     * A player has to be in *that club's* squad for *that season*. Not merely in the
     * organization, and not merely in some squad somewhere: the check is what stops a goal
     * being credited to somebody who was not on the pitch.
     */
    private function isRostered(Fixture $fixture, Team $team, Player $player): bool
    {
        return RosterEntry::query()
            ->where('player_id', $player->id)
            ->whereIn('season_team_id', SeasonTeam::query()
                ->select('id')
                ->where('season_id', $fixture->season_id)
                ->where('team_id', $team->id))
            ->exists();
    }
}
