<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fixture_id')->constrained('fixtures')->cascadeOnDelete();
            $table->string('type', 16);
            $table->smallInteger('minute');

            // RESTRICT on all three, deliberately. Deleting one club or one player must not
            // quietly erase their goals from the record: a scorer who cannot be deleted is a
            // scorer whose goals still add up. Deleting a whole organization still works,
            // because that path removes the events first, deepest-first.
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('player_id')->constrained('players')->restrictOnDelete();

            // Only a substitution has one: the player coming on.
            $table->foreignId('related_player_id')->nullable()->constrained('players')->restrictOnDelete();

            $table->timestamps();

            $table->index(['fixture_id', 'type'], 'idx_event_fixture_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
