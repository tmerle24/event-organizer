<?php

namespace App\Services;

use App\Models\Event;

/**
 * Erzeugt die Planungssektionen aus dem planning_template (Spec Abschnitt 4,
 * Schritt 4). Die Aufgaben-Vorschlaege werden NICHT als Task angelegt — sie
 * gehen als inaktive Vorschlagsliste ans Frontend, weil die KI im MVP
 * ausschliesslich vorschlaegt (Spec Abschnitt 7, "Aktionen").
 */
class PlanBuilder
{
    public function sectionsFor(Event $event): array
    {
        $template = config('planning.templates.'.$event->planning_template)
            ?? config('planning.templates.generic');

        return $template['sections'] ?? [];
    }

    public function buildSections(Event $event): void
    {
        if ($event->planSections()->exists()) {
            return;
        }

        $sort = 0;

        foreach ($this->sectionsFor($event) as $key => $taskKeys) {
            $event->planSections()->create([
                'key' => $key,
                'title' => __('planning.sections.'.$key),
                'sort' => $sort++,
            ]);
        }
    }

    /**
     * Vorschlaege pro Sektion, gefiltert um bereits existierende Aufgaben,
     * damit "Uebernehmen" nichts doppelt anlegt.
     *
     * @return array<string, list<string>>
     */
    public function suggestionsFor(Event $event): array
    {
        $event->loadMissing('tasks');
        $existing = $event->tasks->pluck('title')
            ->map(fn ($t) => mb_strtolower(trim($t)))
            ->all();

        $out = [];

        foreach ($this->sectionsFor($event) as $key => $taskKeys) {
            $titles = collect($taskKeys)
                ->map(fn ($taskKey) => __('planning.tasks.'.$taskKey))
                ->reject(fn ($title) => in_array(mb_strtolower($title), $existing, true))
                ->values()
                ->all();

            if ($titles) {
                $out[$key] = $titles;
            }
        }

        return $out;
    }
}
