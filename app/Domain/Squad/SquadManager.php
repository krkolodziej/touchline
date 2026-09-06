<?php

declare(strict_types=1);

namespace App\Domain\Squad;

use App\Enums\PlayerPosition;
use App\Exceptions\ConflictException;
use App\Models\Player;
use App\Models\RosterEntry;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who may be registered for a season, and who may be in a squad.
 *
 * These rules live here rather than in a form request for one reason: they all need to know
 * *which organization is asking*, and a rule object has no way to find that out. A rule
 * validates a value; this validates a value against a context.
 */
class SquadManager
{
    public function registerTeam(Season $season, Team $team): SeasonTeam
    {
        // The club has to come from the same organization as the competition. Without this,
        // an administrator could register any club whose id they could guess — the ids are
        // sequential and the endpoint is otherwise perfectly legitimate.
        if ($team->organization_id !== $season->league->organization_id) {
            throw ValidationException::withMessages([
                'team_id' => 'That club belongs to another organization.',
            ]);
        }

        $registered = SeasonTeam::query()
            ->where('season_id', $season->id)
            ->where('team_id', $team->id)
            ->exists();

        if ($registered) {
            throw new ConflictException(
                'That club is already registered for this season.',
                'already_registered',
            );
        }

        return SeasonTeam::query()->create([
            'season_id' => $season->id,
            'team_id' => $team->id,
        ]);
    }

    public function withdrawTeam(SeasonTeam $seasonTeam): void
    {
        $seasonTeam->delete();
    }

    public function addToSquad(
        SeasonTeam $seasonTeam,
        Player $player,
        ?int $shirtNumber,
        ?PlayerPosition $position,
        bool $captain,
    ): RosterEntry {
        if ($player->organization_id !== $seasonTeam->season->league->organization_id) {
            throw ValidationException::withMessages([
                'player_id' => 'That player belongs to another organization.',
            ]);
        }

        $already = RosterEntry::query()
            ->where('season_team_id', $seasonTeam->id)
            ->where('player_id', $player->id)
            ->exists();

        if ($already) {
            throw new ConflictException('That player is already in this squad.', 'already_in_squad');
        }

        return DB::transaction(function () use ($seasonTeam, $player, $shirtNumber, $position, $captain): RosterEntry {
            $entry = RosterEntry::query()->create([
                'season_team_id' => $seasonTeam->id,
                'player_id' => $player->id,
            ]);

            $this->apply($seasonTeam, $entry, $shirtNumber, $position, $captain);

            return $entry;
        });
    }

    public function updateSquadEntry(
        RosterEntry $entry,
        ?int $shirtNumber,
        ?PlayerPosition $position,
        bool $captain,
    ): void {
        DB::transaction(function () use ($entry, $shirtNumber, $position, $captain): void {
            $this->apply($entry->seasonTeam, $entry, $shirtNumber, $position, $captain);
        });
    }

    public function removeFromSquad(RosterEntry $entry): void
    {
        $entry->delete();
    }

    /**
     * The shared write path, so the shirt-number check and the captain handover are stated
     * once rather than in both the add branch and the update branch.
     */
    private function apply(
        SeasonTeam $seasonTeam,
        RosterEntry $entry,
        ?int $shirtNumber,
        ?PlayerPosition $position,
        bool $captain,
    ): void {
        if ($shirtNumber !== null && $this->shirtNumberTaken($seasonTeam, $shirtNumber, $entry->id)) {
            // Checked rather than left to the unique index, because an integrity violation
            // surfaces as a 500. The index is still the guarantee; this is the message.
            throw ValidationException::withMessages([
                'shirt_number' => sprintf('Number %d is already worn in this squad.', $shirtNumber),
            ]);
        }

        $entry->shirt_number = $shirtNumber;
        $entry->position = $position;

        if ($captain) {
            // Naming a captain demotes the previous one instead of failing. A squad has
            // exactly one, and refusing would make the operator go and find out who
            // currently holds it — a rule the computer is better placed to keep.
            $current = RosterEntry::query()
                ->where('season_team_id', $seasonTeam->id)
                ->where('captain', true)
                ->whereKeyNot($entry->id)
                ->first();

            // Demoted in its own statement, before the new one is promoted. The order is
            // not decoration: the partial unique index is checked as each statement runs,
            // so two captains existing even for the length of one statement is refused.
            // Both writes sit inside the caller's transaction, so the handover is atomic.
            $current?->forceFill(['captain' => false])->save();
        }

        $entry->captain = $captain;
        $entry->save();
    }

    private function shirtNumberTaken(SeasonTeam $seasonTeam, int $shirtNumber, ?int $exceptId): bool
    {
        return RosterEntry::query()
            ->where('season_team_id', $seasonTeam->id)
            ->where('shirt_number', $shirtNumber)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }
}
