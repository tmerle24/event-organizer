<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PlanSection;
use App\Services\EventPresenter;
use Illuminate\Http\Request;

class PlanSectionController extends Controller
{
    public function __construct(private readonly EventPresenter $presenter) {}

    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
        ]);

        $event->planSections()->create([
            'key' => 'custom',
            'title' => $validated['title'],
            'sort' => (int) $event->planSections()->max('sort') + 1,
        ]);

        $event->enterPlanningIfNeeded();
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())], 201);
    }

    public function update(Request $request, Event $event, PlanSection $section)
    {
        abort_unless($section->event_id === $event->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
        ]);

        $section->update($validated);
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    /**
     * Sektionen sind Vorschlaege und einzeln entfernbar (Spec Abschnitt 4,
     * Schritt 4). Aufgaben darin bleiben erhalten und ruecken in den
     * sektionslosen Bereich — Loeschen einer Ueberschrift darf keine Arbeit
     * vernichten.
     */
    public function destroy(Event $event, PlanSection $section)
    {
        abort_unless($section->event_id === $event->id, 404);

        $section->delete();
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }
}
