<?php

namespace App\Http\Controllers;

use App\Models\DateOption;
use App\Models\Event;
use App\Services\DateOptionSuggester;
use App\Services\EventNotifier;
use App\Services\EventPresenter;
use App\Services\PlanBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Organisator-Bereich. Autorisierung ist ausschliesslich der manage_token in
 * der URL (Route-Model-Binding in AppServiceProvider) — analog zu SimpleVoter
 * und Wisherful. Alle axios-Endpunkte antworten mit JSON, nie mit
 * redirect()->back(), sonst gibt es Redirect-Loops mit Inertia.
 */
class EventManageController extends Controller
{
    public function __construct(
        private readonly EventPresenter $presenter,
        private readonly PlanBuilder $plan,
        private readonly DateOptionSuggester $suggester,
        private readonly EventNotifier $notifier,
    ) {}

    public function show(Event $event)
    {
        $this->closeIfPast($event);

        // Nur pageTitle, kein ogTitle: im eigenen Browser-Tab ist der
        // Eventname hilfreich, in einer Link-Vorschau hat er nichts verloren —
        // der Verwaltungslink gehört nicht in einen Gruppenchat.
        return Inertia::render('Event/Manage', [
            'event' => $this->presenter->forManage($event),
        ])->withViewData(['pageTitle' => $event->title.' – '.config('app.name')]);
    }

    public function data(Event $event)
    {
        $this->closeIfPast($event);

        return response()->json(['event' => $this->presenter->forManage($event)]);
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:200'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'mode' => ['sometimes', 'in:dates,list,both'],
            'planning_template' => ['sometimes', 'in:barbecue,dinner,party,trip,meeting,generic'],
            'participant_count_hint' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'organizer_email' => ['sometimes', 'nullable', 'email', 'max:180'],
            'organizer_name' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $event->update($validated);
        $event->touchActivity();

        // Wird der Modus auf "Liste" erweitert, muss der Planungsbereich
        // existieren, damit die Sektionen nicht leer bleiben.
        if ($event->hasList() && ! $event->planSections()->exists()) {
            $this->plan->buildSections($event);
        }

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    public function storeOption(Request $request, Event $event)
    {
        $validated = $request->validate([
            'day' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'all_day' => ['nullable', 'boolean'],
        ]);

        $allDay = (bool) ($validated['all_day'] ?? false) || empty($validated['time']);
        $day = CarbonImmutable::parse($validated['day'], $event->timezone)->startOfDay();

        $start = $allDay
            ? $day
            : $day->setTimeFromTimeString($validated['time']);

        $event->dateOptions()->create([
            'starts_at_utc' => $start->setTimezone('UTC'),
            'day' => $day->toDateString(),
            'all_day' => $allDay,
            'sort' => (int) $event->dateOptions()->max('sort') + 1,
        ]);

        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())], 201);
    }

    public function updateOption(Request $request, Event $event, DateOption $option)
    {
        abort_unless($option->event_id === $event->id, 404);

        $validated = $request->validate([
            'day' => ['sometimes', 'date'],
            'time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'all_day' => ['sometimes', 'boolean'],
        ]);

        $day = CarbonImmutable::parse($validated['day'] ?? $option->day ?? $option->starts_at_utc, $event->timezone)
            ->startOfDay();

        $allDay = array_key_exists('all_day', $validated)
            ? (bool) $validated['all_day']
            : $option->all_day;

        $time = array_key_exists('time', $validated)
            ? $validated['time']
            : $option->starts_at_utc->setTimezone($event->timezone)->format('H:i');

        if (! $time) {
            $allDay = true;
        }

        $start = $allDay ? $day : $day->setTimeFromTimeString($time);

        $option->update([
            'starts_at_utc' => $start->setTimezone('UTC'),
            'day' => $day->toDateString(),
            'all_day' => $allDay,
        ]);

        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    public function destroyOption(Event $event, DateOption $option)
    {
        abort_unless($option->event_id === $event->id, 404);

        // Wird der bestaetigte Termin geloescht, faellt das Event zurueck in
        // die Sammelphase — sonst zeigt die Seite ein Ergebnis ohne Termin.
        if ($event->decided_option_id === $option->id) {
            $event->update([
                'decided_option_id' => null,
                'status' => Event::STATUS_COLLECTING,
            ]);
        }

        $option->delete();
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    public function suggestOptions(Request $request, Event $event)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'time_of_day' => ['nullable', 'in:morning,midday,afternoon,evening'],
            'preferred_days' => ['nullable', 'array'],
            'preferred_days.*' => ['in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        ]);

        $suggestions = $this->suggester->suggest([
            'date_range' => ['from' => $validated['from'], 'to' => $validated['to']],
            'time_of_day' => $validated['time_of_day'] ?? 'evening',
            'preferred_days' => $validated['preferred_days'] ?? [],
        ], $event->timezone);

        $sort = (int) $event->dateOptions()->max('sort');
        $existing = $event->dateOptions()->pluck('starts_at_utc')
            ->map(fn ($d) => $d->toIso8601String())
            ->all();

        foreach ($suggestions as $option) {
            if (in_array($option['starts_at_utc']->toIso8601String(), $existing, true)) {
                continue;
            }

            $event->dateOptions()->create([...$option, 'sort' => ++$sort]);
        }

        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    /**
     * Spec Abschnitt 4, Schritt 3: der Organisator bestaetigt manuell — die
     * App entscheidet nie selbst.
     */
    public function decide(Request $request, Event $event)
    {
        $validated = $request->validate([
            'date_option_id' => ['required', 'integer'],
            'notify' => ['nullable', 'boolean'],
        ]);

        $option = $event->dateOptions()->findOrFail($validated['date_option_id']);

        $event->update([
            'decided_option_id' => $option->id,
            'status' => Event::STATUS_DECIDED,
        ]);

        $this->plan->buildSections($event);
        $event->touchActivity();

        $notified = ($validated['notify'] ?? true)
            ? $this->notifier->announceDecision($event)
            : 0;

        return response()->json([
            'event' => $this->presenter->forManage($event->fresh()),
            'notified' => $notified,
        ]);
    }

    public function undecide(Event $event)
    {
        $event->update([
            'decided_option_id' => null,
            'status' => Event::STATUS_COLLECTING,
        ]);
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    public function cancel(Event $event)
    {
        $event->update(['status' => Event::STATUS_CANCELLED]);
        $notified = $this->notifier->announceCancellation($event);

        return response()->json([
            'event' => $this->presenter->forManage($event->fresh()),
            'notified' => $notified,
        ]);
    }

    public function reopen(Event $event)
    {
        $event->update([
            'status' => $event->decided_option_id ? Event::STATUS_DECIDED : Event::STATUS_COLLECTING,
        ]);
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forManage($event->fresh())]);
    }

    public function destroy(Event $event)
    {
        // Cascade-Delete raeumt Teilnehmer, Antworten, Aufgaben und
        // Mail-Protokolle mit ab (Spec Abschnitt 11).
        $event->delete();

        return response()->json(['deleted' => true]);
    }

    public function invite(Request $request, Event $event)
    {
        $validated = $request->validate([
            'emails' => ['required', 'array', 'max:50'],
            'emails.*' => ['email', 'max:180'],
        ]);

        $sent = 0;

        foreach ($validated['emails'] as $email) {
            if ($this->notifier->invite($event, $email)) {
                $sent++;
            }
        }

        $event->touchActivity();

        return response()->json(['sent' => $sent]);
    }

    public function sendManageLink(Request $request, Event $event)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:180'],
        ]);

        $event->update(['organizer_email' => $validated['email']]);
        $sent = $this->notifier->sendManageLink($event, $validated['email']);

        return response()->json(['sent' => $sent]);
    }

    /**
     * Spec Abschnitt 3: closed = Event liegt in der Vergangenheit, read-only.
     * Wird beim Aufruf ausgewertet statt per Cronjob — genauer und billiger.
     */
    private function closeIfPast(Event $event): void
    {
        if (! in_array($event->status, [Event::STATUS_DECIDED, Event::STATUS_PLANNING], true)) {
            return;
        }

        $option = $event->decidedOption;

        if ($option && $option->starts_at_utc->addDay()->isPast()) {
            $event->update(['status' => Event::STATUS_CLOSED]);
        }
    }
}
