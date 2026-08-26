<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use App\Services\EventPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Teilnehmerverwaltung. Spec Abschnitt 2: Fremde Antworten sind sichtbar, aber
 * nicht editierbar — umbenennen, mergen und entfernen kann ausschliesslich der
 * Organisator, deshalb haengen diese Routen am manage_token.
 */
class ParticipantController extends Controller
{
    public function __construct(private readonly EventPresenter $presenter) {}

    public function update(Request $request, Event $event, Participant $participant)
    {
        abort_unless($participant->event_id === $event->id, 404);

        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:180'],
            'is_required' => ['sometimes', 'boolean'],
        ]);

        $participant->update($validated);
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    public function destroy(Event $event, Participant $participant)
    {
        abort_unless($participant->event_id === $event->id, 404);

        $participant->delete();
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    /**
     * Zweitgeraet ohne Cookie erzeugt einen zweiten Teilnehmer (Spec Abschnitt
     * 2). Der Merge zieht dessen Antworten und Aufgaben herueber und behaelt
     * bei Konflikten die des Ziels — der Organisator hat den Merge ausgeloest,
     * also gewinnt der Eintrag, den er behalten wollte.
     */
    public function merge(Request $request, Event $event, Participant $participant)
    {
        abort_unless($participant->event_id === $event->id, 404);

        $validated = $request->validate([
            'into_participant_id' => ['required', 'integer'],
        ]);

        $target = $event->participants()->findOrFail($validated['into_participant_id']);
        abort_if($target->id === $participant->id, 422, 'Cannot merge a participant into itself.');

        DB::transaction(function () use ($participant, $target, $event) {
            $existing = $target->availabilities()->pluck('date_option_id')->all();

            $participant->availabilities()
                ->whereNotIn('date_option_id', $existing)
                ->update(['participant_id' => $target->id]);

            $event->tasks()
                ->where('assignee_participant_id', $participant->id)
                ->update(['assignee_participant_id' => $target->id]);

            if (! $target->email && $participant->email) {
                $target->update(['email' => $participant->email]);
            }

            if ($participant->is_required) {
                $target->update(['is_required' => true]);
            }

            $participant->delete();
        });

        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }
}
