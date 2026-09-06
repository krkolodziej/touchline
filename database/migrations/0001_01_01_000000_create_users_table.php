<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // Stored lower-cased and trimmed by the model, so the unique index is the
            // whole rule rather than half of it: without normalisation "Ada@example.com"
            // and "ada@example.com" are two accounts that can both sign in as one person.
            $table->string('email', 180)->unique('uniq_user_email');
            $table->string('password');

            // Empty rather than nullable. A name nobody gave is an empty name, and a
            // nullable column would make every reader decide what null renders as.
            $table->string('first_name', 100)->default('');
            $table->string('last_name', 100)->default('');

            $table->timestamps();
        });

        // Sessions live in the database, not in a cookie: the payload is server-side, so
        // signing out actually ends the session rather than asking the browser to forget it.
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
