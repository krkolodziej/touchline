<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);

            // Globally unique rather than unique per anything: the organization *is* the
            // tenant, so there is nothing above it to scope a slug to.
            $table->string('slug', 64)->unique('uniq_organization_slug');

            // RESTRICT, not CASCADE. Deleting the account that created an organization must
            // fail loudly rather than take the competition with it.
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();

            $table->timestamps();
        });

        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 10);
            $table->timestamps();

            // This row is the security boundary: every scoped query joins through it, and
            // one person may hold exactly one position in one organization.
            $table->unique(['organization_id', 'user_id'], 'uniq_membership_organization_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
    }
};
