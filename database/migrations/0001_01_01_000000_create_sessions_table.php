<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Architektur-Prinzip "Auth ohne Auth": Es gibt bewusst keine users-Tabelle.
 * Zugriff wird ausschliesslich ueber Tokens geregelt (siehe CLAUDE.md).
 * Die sessions-Tabelle wird trotzdem gebraucht, weil SESSION_DRIVER=database
 * (CSRF-Schutz fuer die axios-Endpunkte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
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
    }
};
