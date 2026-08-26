<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Task;
use App\Services\EventPresenter;
use Illuminate\Http\Request;

/**
 * Aufgaben (Spec Abschnitt 4, Schritt 5). Erreichbar sowohl ueber den
 * Manage- als auch den Public-Kontext: Teilnehmer duerfen Aufgaben anlegen,
 * uebernehmen und abhaken — nur ihre eigene Zuweisung, nicht die anderer.
 */
class TaskController extends Controller
{
    public function __construct(private readonly EventPresenter $presenter) {}

    public function store(Request $request, Event $event)
    {
        abort_if($event->isReadOnly(), 422, 'Event is closed.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'plan_section_id' => ['nullable', 'integer'],
            'assignee_participant_id' => ['nullable', 'integer'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        $sectionId = $this->resolveSectionId($event, $validated['plan_section_id'] ?? null);
        $assigneeId = $this->resolveAssigneeId($request, $event, $validated);

        $event->tasks()->create([
            'title' => $validated['title'],
            'plan_section_id' => $sectionId,
            'assignee_participant_id' => $assigneeId,
            'sort' => (int) $event->tasks()->max('sort') + 1,
        ]);

        $event->enterPlanningIfNeeded();
        $event->touchActivity();

        return response()->json(['event' => $this->present($request, $event->fresh())], 201);
    }

    public function update(Request $request, Event $event, Task $task)
    {
        abort_unless($task->event_id === $event->id, 404);
        abort_if($event->isReadOnly(), 422, 'Event is closed.');

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', 'in:open,done'],
            'plan_section_id' => ['sometimes', 'nullable', 'integer'],
            'assignee_participant_id' => ['sometimes', 'nullable', 'integer'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        $attributes = array_intersect_key($validated, array_flip(['title', 'status']));

        if (array_key_exists('plan_section_id', $validated)) {
            $attributes['plan_section_id'] = $this->resolveSectionId($event, $validated['plan_section_id']);
        }

        if (array_key_exists('assignee_participant_id', $validated)) {
            $attributes['assignee_participant_id'] = $this->guardAssignee(
                $request,
                $event,
                $task,
                $validated['assignee_participant_id']
            );
        }

        $task->update($attributes);
        $event->enterPlanningIfNeeded();
        $event->touchActivity();

        return response()->json(['event' => $this->present($request, $event->fresh())]);
    }

    public function destroy(Request $request, Event $event, Task $task)
    {
        abort_unless($task->event_id === $event->id, 404);

        // Im Public-Kontext darf nur geloescht werden, was der Teilnehmer
        // selbst uebernommen hat — sonst raeumt jemand fremde Aufgaben ab.
        if (! $this->isManageContext($request)) {
            $me = $this->participantFrom($request, $event);
            abort_unless($me && $task->assignee_participant_id === $me->id, 403);
        }

        $task->delete();
        $event->touchActivity();

        return response()->json(['event' => $this->present($request, $event->fresh())]);
    }

    /**
     * Uebernimmt einen Template-Vorschlag als echte Aufgabe. Zuweisung passiert
     * dabei bewusst nicht — die KI/das Template schlaegt vor, verteilt nie
     * selbst (Spec Abschnitt 4, Schritt 5).
     */
    public function adopt(Request $request, Event $event)
    {
        abort_if($event->isReadOnly(), 422, 'Event is closed.');

        $validated = $request->validate([
            'titles' => ['required', 'array', 'max:30'],
            'titles.*' => ['string', 'max:160'],
            'plan_section_id' => ['nullable', 'integer'],
        ]);

        $sectionId = $this->resolveSectionId($event, $validated['plan_section_id'] ?? null);
        $sort = (int) $event->tasks()->max('sort');

        foreach ($validated['titles'] as $title) {
            $event->tasks()->create([
                'title' => $title,
                'plan_section_id' => $sectionId,
                'sort' => ++$sort,
            ]);
        }

        $event->enterPlanningIfNeeded();
        $event->touchActivity();

        return response()->json(['event' => $this->present($request, $event->fresh())], 201);
    }

    private function resolveSectionId(Event $event, mixed $sectionId): ?int
    {
        if (! $sectionId) {
            return null;
        }

        return $event->planSections()->whereKey($sectionId)->value('id');
    }

    private function resolveAssigneeId(Request $request, Event $event, array $validated): ?int
    {
        if (! array_key_exists('assignee_participant_id', $validated) || ! $validated['assignee_participant_id']) {
            return null;
        }

        return $this->guardAssignee($request, $event, null, $validated['assignee_participant_id']);
    }

    /**
     * Zuweisung nur an existierende Teilnehmer des Events. Aus dem
     * Public-Kontext heraus darf sich ausserdem nur der Teilnehmer selbst
     * eintragen oder eine freie Aufgabe uebernehmen.
     */
    private function guardAssignee(Request $request, Event $event, ?Task $task, mixed $participantId): ?int
    {
        if (! $participantId) {
            if (! $this->isManageContext($request) && $task) {
                $me = $this->participantFrom($request, $event);
                abort_unless($me && $task->assignee_participant_id === $me->id, 403);
            }

            return null;
        }

        $id = $event->participants()->whereKey($participantId)->value('id');
        abort_unless($id, 422, 'Unknown participant.');

        if (! $this->isManageContext($request)) {
            $me = $this->participantFrom($request, $event);
            abort_unless($me && $me->id === $id, 403);
            abort_unless(! $task || ! $task->assignee_participant_id || $task->assignee_participant_id === $me->id, 403);
        }

        return $id;
    }

    private function participantFrom(Request $request, Event $event): ?Participant
    {
        $token = $request->input('token') ?? $request->header('X-Participant-Token');

        return $token ? $event->participants()->where('token', $token)->first() : null;
    }

    private function isManageContext(Request $request): bool
    {
        return str_starts_with($request->route()?->getName() ?? '', 'manage.');
    }

    private function present(Request $request, Event $event): array
    {
        return $this->isManageContext($request)
            ? $this->presenter->forManage($event)
            : $this->presenter->forPublic($event, $this->participantFrom($request, $event));
    }
}
