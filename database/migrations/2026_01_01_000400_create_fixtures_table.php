<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * There is no `matches` table and no Match model. `match` is a reserved word in PHP 8, and
 * a fixture is what the calendar holds anyway: the pairing, when it is played, and what
 * happened. One row is the appointment and the record of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();

            $table->smallInteger('round_number');

            // 1 or 2. The second leg is the same pairing with the sides swapped, which is
            // why the unique index below is on the ordered pair rather than the unordered
            // one: A-v-B and B-v-A are two different fixtures.
            $table->smallInteger('leg')->default(1);

            $table->timestamp('kick_off_at')->nullable();
            $table->string('status', 16)->default('SCHEDULED');

            // The score is never typed in. It moves only when a goal is recorded, in the
            // same transaction as the event, so it cannot disagree with its own history.
            $table->smallInteger('home_score')->default(0);
            $table->smallInteger('away_score')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['season_id', 'home_team_id', 'away_team_id'],
                'uniq_fixture_season_direction',
            );

            $table->index(['season_id', 'round_number'], 'idx_fixture_season_round');
            $table->index(['season_id', 'status'], 'idx_fixture_season_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
