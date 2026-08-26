<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Abschnitt 8: Dedupe-Key pro (Empfaenger, Typ, Event), damit Retries
 * keine Doppelmails erzeugen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('type', 40);
            $table->string('dedupe_key')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_notifications');
    }
};
