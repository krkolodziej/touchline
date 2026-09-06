<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->string('name', 32);

            // Dates that are dates. A timestamp would invent a midnight and a timezone
            // neither value has.
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'name'], 'uniq_season_league_name');
        });

        // A club's registration for one season. This row, not a column on the club, is what
        // makes "which league does Stal play in" a question with a season in it.
        Schema::create('season_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['season_id', 'team_id'], 'uniq_season_team');
        });

        Schema::create('roster_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_team_id')->constrained('season_teams')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->smallInteger('shirt_number')->nullable();
            $table->string('position', 16)->nullable();
            $table->boolean('captain')->default(false);
            $table->timestamps();

            $table->unique(['season_team_id', 'player_id'], 'uniq_roster_squad_player');

            // NULLs are distinct in SQL, so any number of unnumbered players coexist while
            // two number nines do not. That is the behaviour wanted, and it is free.
            $table->unique(['season_team_id', 'shirt_number'], 'uniq_roster_squad_shirt');
        });

        // At most one captain per squad, enforced by the database rather than by everybody
        // remembering. A partial index is the only way to say "unique among the rows where
        // this is true", and the schema builder has no vocabulary for it.
        DB::statement('CREATE UNIQUE INDEX uniq_roster_one_captain ON roster_entries (season_team_id) WHERE captain');
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_entries');
        Schema::dropIfExists('season_teams');
        Schema::dropIfExists('seasons');
    }
};
