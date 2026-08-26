<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('email')->nullable();
            // Client-seitig erzeugter Geraete-Token (LocalStorage), 32 Zeichen.
            $table->string('token', 64)->index();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_organizer')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
