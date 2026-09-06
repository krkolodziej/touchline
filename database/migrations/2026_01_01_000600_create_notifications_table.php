<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('title', 160);
            $table->string('body', 320);

            // A path, never an absolute URL. A stored hostname is a hostname that has to be
            // right behind a proxy, in tests and in a container — and wrong forever in rows
            // written before somebody moved the application.
            $table->string('link', 255);

            /**
             * The one thing that makes delivery at-most-once.
             *
             * A queue is at-least-once by nature: a worker that crashes between doing the
             * work and marking the job done will run it again, correctly. So the key
             * describes the *fact* — "this match finished, and this person should hear about
             * it" — rather than the attempt, and the unique index below turns a redelivery
             * into a no-op instead of a second bell.
             */
            $table->string('dedupe_key', 191)->unique('uniq_notification_dedupe');

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at', 'created_at'], 'idx_notification_recipient_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
