<?php

use App\Models\Event;
use Illuminate\Support\Facades\Schedule;

/*
 * Retention (Spec Abschnitt 11): Events werden 12 Monate nach der letzten
 * Aktivitaet geloescht. Cascade-Delete raeumt Teilnehmer, Antworten,
 * Aufgaben und Mail-Protokolle automatisch mit ab.
 */
Schedule::call(function () {
    Event::whereNotNull('delete_after')
        ->where('delete_after', '<', now())
        ->each->delete();
})->daily()->name('purge-expired-events');
