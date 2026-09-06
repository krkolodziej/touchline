<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 64);
            $table->text('description')->default('');
            $table->timestamps();

            // Unique per organization, not globally. Two associations may each run a
            // "District League", and neither has to know the other exists.
            $table->unique(['organization_id', 'slug'], 'uniq_league_organization_slug');
        });

        // A club has no league. It is registered once and reused: promoted, relegated, or
        // entered in two competitions at the same time. Which league it plays in is a fact
        // about a season, and lives on the season's registration row.
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 64);

            // A short name has to fit a table column. Empty means "use the full name", which
            // is a better default than making everybody invent an abbreviation.
            $table->string('short_name', 32)->default('');
            $table->timestamps();

            $table->unique(['organization_id', 'slug'], 'uniq_team_organization_slug');
        });

        // A player belongs to the organization, not to a club. Which club, in which season,
        // wearing which number, is a squad fact and arrives with rosters.
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);

            // Nullable on purpose: players are routinely registered before anybody has their
            // date of birth, and a required field would be filled in with a guess.
            $table->date('date_of_birth')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'last_name'], 'idx_player_organization_last_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('leagues');
    }
};
