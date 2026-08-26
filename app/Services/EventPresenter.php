<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Participant;

/**
 * Baut die JSON-Shapes fuer Manage- und Public-Ansicht.
 *
 * Wichtig: Die Public-Shape wird IMMER explizit zusammengesetzt und niemals
 * aus dem Model serialisiert — sonst wandert irgendwann der manage_token
 * ueber eine oeffentliche Route nach draussen.
 */
class EventPresenter
{
    public function __construct(
        private readonly RankingService $ranking,
        private readonly PlanBuilder $plan,
    ) {}

    public function forManage(Event $event): array
    {
        return [
            ...$this->common($event),
            'manage_token' => $event->manage_token,
            'organizer_email' => $event->organizer_email,
            'organizer_name' => $event->organizer_name,
            'ai_meta' => $event->ai_meta,
            'manage_url' => $event->manageUrl(),
            'participants' => $event->participants->map(fn (Participant $p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'email' => $p->email,
                'is_required' => $p->is_required,
                'is_organizer' => $p->is_organizer,
                'answered_count' => $p->availabilities->count(),
            ])->values()->all(),
        ];
    }

    public function forPublic(Event $event, ?Participant $me = null): array
    {
        return [
            ...$this->common($event),
            'me' => $me ? [
                'id' => $me->id,
                'display_name' => $me->display_name,
                'email' => $me->email,
                'is_required' => $me->is_required,
            ] : null,
            'participants' => $event->participants->map(fn (Participant $p) => [
                'id' => $p->id,
                'display_name' => $p->display_name,
                'is_required' => $p->is_required,
                'is_organizer' => $p->is_organizer,
            ])->values()->all(),
        ];
    }

    private function common(Event $event): array
    {
        $event->loadMissing([
            'dateOptions.availabilities',
            'participants.availabilities',
            'planSections',
            'tasks.assignee',
        ]);

        $ranked = collect($this->ranking->rank($event))->keyBy('id');

        return [
            'public_token' => $event->public_token,
            'public_url' => $event->publicUrl(),
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'event_type' => $event->event_type,
            'planning_template' => $event->planning_template,
            'status' => $event->status,
            'mode' => $event->mode,
            'timezone' => $event->timezone,
            'decided_option_id' => $event->decided_option_id,
            'participant_count_hint' => $event->participant_count_hint,
            'participant_count' => $event->participants->count(),
            'best_match_id' => $this->ranking->bestMatchId($event),
            'answers_needed' => $this->answersNeeded($event),

            'date_options' => $event->dateOptions->map(function ($option) use ($ranked) {
                $stats = $ranked[$option->id] ?? null;

                return [
                    'id' => $option->id,
                    'starts_at_utc' => $option->starts_at_utc?->toIso8601String(),
                    'ends_at_utc' => $option->ends_at_utc?->toIso8601String(),
                    'day' => $option->day?->toDateString(),
                    'all_day' => $option->all_day,
                    'sort' => $option->sort,
                    'yes_count' => $stats['yes_count'] ?? 0,
                    'maybe_count' => $stats['maybe_count'] ?? 0,
                    'no_count' => $stats['no_count'] ?? 0,
                    'open_count' => $stats['open_count'] ?? 0,
                    'blocked' => $stats['blocked'] ?? false,
                    'score' => $stats['score'] ?? 0,
                    'votes' => $option->availabilities
                        ->mapWithKeys(fn ($a) => [$a->participant_id => $a->value])
                        ->all(),
                ];
            })->values()->all(),

            'ranking' => array_map(fn ($row) => $row['id'], $this->ranking->rank($event)),

            'plan_sections' => $event->planSections->map(fn ($section) => [
                'id' => $section->id,
                'key' => $section->key,
                'title' => $section->title,
                'sort' => $section->sort,
            ])->values()->all(),

            'tasks' => $event->tasks->map(fn ($task) => [
                'id' => $task->id,
                'plan_section_id' => $task->plan_section_id,
                'title' => $task->title,
                'status' => $task->status,
                'sort' => $task->sort,
                'assignee_participant_id' => $task->assignee_participant_id,
                'assignee_name' => $task->assignee?->display_name,
            ])->values()->all(),

            'task_suggestions' => $this->plan->suggestionsFor($event),
        ];
    }

    /**
     * Wie viele Antworten noch fehlen, bevor ueberhaupt ein Best Match
     * ausgewiesen wird (Spec Abschnitt 5). 0 = Quorum erreicht.
     */
    private function answersNeeded(Event $event): int
    {
        $total = $event->participants->count();

        if ($total === 0) {
            return 1;
        }

        $answered = $event->participants
            ->filter(fn ($p) => $p->availabilities->isNotEmpty())
            ->count();

        return max(0, (int) ceil($total * RankingService::BEST_MATCH_QUORUM) - $answered);
    }
}
