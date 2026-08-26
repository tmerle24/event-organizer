<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Abschnitt 6: alles in UTC speichern. Ganztaegige Optionen speichern
 * nur das Datum (all_day = true) und werden nie umgerechnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at_utc');
            $table->timestampTz('ends_at_utc')->nullable();
            $table->date('day')->nullable();
            $table->boolean('all_day')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_options');
    }
};
