<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\Event;
use App\Models\Participant;
use App\Services\EventPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Teilnehmer-Ansicht ueber den public_token. Identifikation laeuft ueber einen
 * client-seitig erzeugten Geraete-Token (LocalStorage) — kein Login, kein
 * Cookie-Banner noetig, weil der Token technisch erforderlich ist.
 */
class PublicEventController extends Controller
{
    public function __construct(private readonly EventPresenter $presenter) {}

    public function show(Request $request, Event $event)
    {
        return Inertia::render('Event/Public', [
            'event' => $this->presenter->forPublic($event, $this->resolveParticipant($request, $event)),
        ]);
    }

    public function state(Request $request, Event $event)
    {
        return response()->json([
            'event' => $this->presenter->forPublic($event, $this->resolveParticipant($request, $event)),
        ]);
    }

    /**
     * Erste Antwort erzeugt den Teilnehmer (Spec Abschnitt 2). Name ist
     * Pflicht, E-Mail optional — bewusst, weil die Huerde sonst steigt und die
     * Response Rate faellt.
     */
    public function join(Request $request, Event $event)
    {
        if ($request->filled('website')) {
            abort(422, 'Invalid submission.');
        }

        abort_if($event->isReadOnly(), 422, 'Event is closed.');

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:180'],
            'token' => ['required', 'string', 'min:16', 'max:64'],
        ]);

        $participant = $event->participants()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'display_name' => $validated['display_name'],
                'email' => $validated['email'] ?? null,
            ]
        );

        $event->touchActivity();

        return response()->json([
            'event' => $this->presenter->forPublic($event->fresh(), $participant),
        ], 201);
    }

    /**
     * Setzt die Verfuegbarkeiten eines Teilnehmers. Nicht uebermittelte
     * Optionen bleiben "offen" — der Default ist ausdruecklich nicht
     * "kann nicht" (Spec Abschnitt 4, Schritt 2).
     */
    public function storeAvailability(Request $request, Event $event)
    {
        abort_if($event->isReadOnly(), 422, 'Event is closed.');

        $validated = $request->validate([
            'token' => ['required', 'string', 'min:16', 'max:64'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'in:yes,no,maybe'],
        ]);

        $participant = $event->participants()->where('token', $validated['token'])->firstOrFail();
        $optionIds = $event->dateOptions()->pluck('id')->all();

        DB::transaction(function () use ($validated, $participant, $optionIds) {
            foreach ($validated['answers'] as $optionId => $value) {
                if (! in_array((int) $optionId, $optionIds, true)) {
                    continue;
                }

                if ($value === null) {
                    $participant->availabilities()->where('date_option_id', $optionId)->delete();

                    continue;
                }

                Availability::updateOrCreate(
                    ['date_option_id' => (int) $optionId, 'participant_id' => $participant->id],
                    ['value' => $value]
                );
            }
        });

        $event->touchActivity();

        return response()->json([
            'event' => $this->presenter->forPublic($event->fresh(), $participant->fresh()),
        ]);
    }

    /**
     * Teilnehmer koennen ihre eigene Teilnahme inklusive Daten entfernen
     * (Spec Abschnitt 11).
     */
    public function leave(Request $request, Event $event)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:16', 'max:64'],
        ]);

        $event->participants()->where('token', $validated['token'])->delete();
        $event->touchActivity();

        return response()->json(['event' => $this->presenter->forPublic($event->fresh(), null)]);
    }

    private function resolveParticipant(Request $request, Event $event): ?Participant
    {
        $token = $request->query('t') ?? $request->header('X-Participant-Token');

        if (! $token) {
            return null;
        }

        return $event->participants()->where('token', $token)->first();
    }
}
