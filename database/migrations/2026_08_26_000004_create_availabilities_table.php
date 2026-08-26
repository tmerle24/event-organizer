<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('date_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            // yes | no | maybe — "offen" wird nicht gespeichert (Spec 4/Schritt 2)
            $table->string('value', 5);
            $table->timestamps();

            $table->unique(['date_option_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};
