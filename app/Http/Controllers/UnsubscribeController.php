<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use Inertia\Inertia;

/**
 * Abmeldelink aus jeder Teilnehmer-Mail. Signiert statt Geraete-Token, damit er
 * auf jedem Geraet funktioniert. GET zeigt nur an — Mail-Scanner rufen Links
 * vorab auf, geloescht wird erst per POST (Button oder One-Click-Header).
 */
class UnsubscribeController extends Controller
{
    public function show(Event $event, Participant $participant)
    {
        abort_unless($participant->event_id === $event->id, 404);

        return Inertia::render('Unsubscribe', [
            'title' => $event->title,
            'publicUrl' => $event->publicUrl(),
            'done' => blank($participant->email),
        ])->withViewData(['pageTitle' => $event->title.' – '.config('app.name')]);
    }

    public function destroy(Event $event, Participant $participant)
    {
        abort_unless($participant->event_id === $event->id, 404);

        // nur die Adresse — Name, Antworten und Aufgaben bleiben
        $participant->update(['email' => null]);

        return response()->json(['done' => true]);
    }
}
