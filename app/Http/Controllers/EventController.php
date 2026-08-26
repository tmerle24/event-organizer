<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\AiExtractor;
use App\Services\DateOptionSuggester;
use App\Services\PlanBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Schritt 1 aus Spec Abschnitt 4: ein einziges Eingabefeld auf der Startseite.
 * Kein Formularprozess, keine Chat-Rueckfrage — die Extraktion landet direkt
 * als editierbare Feldzeile im Manage-Screen.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly AiExtractor $extractor,
        private readonly DateOptionSuggester $suggester,
        private readonly PlanBuilder $plan,
    ) {}

    public function store(Request $request)
    {
        // Honeypot — unsichtbares Feld, nur Bots fuellen es aus.
        if ($request->filled('website')) {
            abort(422, 'Invalid submission.');
        }

        $validated = $request->validate([
            'input' => ['required', 'string', 'max:500'],
            'mode' => ['nullable', 'in:dates,list,both'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'organizer_name' => ['nullable', 'string', 'max:80'],
            // Optionaler fester Termin — nur bei einer Organisationsliste,
            // dort gibt es keine Terminfindung.
            'fixed_date' => ['nullable', 'date'],
            'fixed_time' => ['nullable', 'date_format:H:i'],
        ]);

        $timezone = $this->safeTimezone($validated['timezone'] ?? null);
        $extraction = $this->extractor->extract($validated['input'], $timezone);
        $mode = $validated['mode'] ?? Event::MODE_DATES;

        $event = DB::transaction(function () use ($extraction, $mode, $timezone, $validated, $request) {
            $event = Event::create([
                'title' => $extraction['event_name'],
                'event_type' => $extraction['event_type'],
                'planning_template' => $extraction['planning_template'],
                'mode' => $mode,
                'timezone' => $timezone,
                'status' => Event::STATUS_COLLECTING,
                'participant_count_hint' => $extraction['participant_count'],
                'organizer_name' => $validated['organizer_name'] ?? null,
                'ai_meta' => [
                    'source' => $extraction['source'],
                    'confidence' => $extraction['confidence'],
                    'raw_input' => mb_substr($validated['input'], 0, 500),
                    'date_range' => $extraction['date_range'],
                    'preferred_days' => $extraction['preferred_days'],
                    'time_of_day' => $extraction['time_of_day'],
                ],
                'creator_ip' => $request->ip(),
            ]);

            if ($event->hasDates()) {
                $this->suggester->applyTo($event, $extraction);
            }

            // Reine Organisationsliste startet sofort im Planungsbereich —
            // es gibt keinen Termin, auf den gewartet werden muesste.
            if ($mode === Event::MODE_LIST) {
                $event->update(['status' => Event::STATUS_PLANNING]);
                $this->plan->buildSections($event);

                if (! empty($validated['fixed_date'])) {
                    $event->setFixedDate($validated['fixed_date'], $validated['fixed_time'] ?? null);
                }
            }

            return $event;
        });

        return response()->json([
            'manage_token' => $event->manage_token,
            'public_token' => $event->public_token,
            'manage_url' => $event->manageUrl(),
            'needs_date_range' => $event->hasDates() && $event->dateOptions()->count() === 0,
        ], 201);
    }

    /**
     * Ohne gueltige Zeitzone wuerde jede spaetere Umrechnung falsch werden;
     * die Browser-Angabe ist deshalb nur ein Vorschlag, kein Vertrauensanker.
     */
    private function safeTimezone(?string $timezone): string
    {
        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return config('app.default_timezone', 'Europe/Berlin');
    }
}
