<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Abschnitt 9, angepasst an das token-basierte Modell:
 * Statt owner_user_id gibt es einen manage_token (64 Zeichen). Damit
 * entfaellt die Account-Pflicht aus Abschnitt 13.1 vollstaendig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('public_token', 12)->unique();
            $table->string('manage_token', 64)->unique();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('event_type', 40)->default('generic');
            $table->string('planning_template', 40)->default('generic');

            // draft | collecting | decided | planning | closed | cancelled
            $table->string('status', 20)->default('collecting')->index();
            // dates | list | both — steuert, welche Bereiche ueberhaupt existieren
            $table->string('mode', 10)->default('dates');

            $table->string('timezone', 64)->default('Europe/Berlin');
            $table->foreignId('decided_option_id')->nullable();

            $table->unsignedSmallInteger('participant_count_hint')->nullable();
            $table->json('ai_meta')->nullable();

            $table->string('organizer_email')->nullable();
            $table->string('organizer_name')->nullable();

            $table->string('creator_ip', 45)->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('delete_after')->nullable()->index();
            $table->timestamp('retention_warned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
