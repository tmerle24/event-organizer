<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltungslink-Adressen werden nur noch versendet, nicht gespeichert.
 * Spalte bleibt fuer die spaetere Loesch-Warnung.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->whereNotNull('organizer_email')->update(['organizer_email' => null]);
    }

    public function down(): void
    {
        // geloeschte Adressen lassen sich nicht wiederherstellen
    }
};
