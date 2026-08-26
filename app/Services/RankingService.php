<?php

namespace App\Services;

use App\Models\Event;

/**
 * Ranking-Regeln aus Spec Abschnitt 5 — eins zu eins abgebildet.
 *
 * Zentrale Darstellungsregel: nie "8/8 verfuegbar", solange Antworten fehlen.
 * Deshalb liefert diese Klasse open_count immer explizit mit; das UI zeigt
 * "6 kann · 2 offen" statt einer Quote.
 */
class RankingService
{
    /**
     * Anteil der Teilnehmer, der geantwortet haben muss, bevor ueberhaupt ein
     * Best Match ausgewiesen wird (Spec Abschnitt 5).
     */
    public const BEST_MATCH_QUORUM = 0.5;

    public function rank(Event $event): array
    {
        $event->loadMissing(['dateOptions.availabilities', 'participants']);

        $participants = $event->participants;
        $participantCount = $participants->count();
        $requiredIds = $participants->where('is_required', true)->pluck('id')->all();

        $rows = $event->dateOptions->map(function ($option) use ($participantCount, $requiredIds) {
            $values = $option->availabilities->pluck('value', 'participant_id');

            $yes = $values->filter(fn ($v) => $v === 'yes')->count();
            $maybe = $values->filter(fn ($v) => $v === 'maybe')->count();
            $no = $values->filter(fn ($v) => $v === 'no')->count();
            $open = max(0, $participantCount - $values->count());

            $blocked = collect($requiredIds)
                ->contains(fn ($id) => ($values[$id] ?? null) === 'no');

            return [
                'id' => $option->id,
                'yes_count' => $yes,
                'maybe_count' => $maybe,
                'no_count' => $no,
                'open_count' => $open,
                'blocked' => $blocked,
                'score' => 2 * $yes + $maybe,
                'starts_at_utc' => $option->starts_at_utc,
                'sort' => $option->sort,
            ];
        })->values();

        // Sortierung: blocked nach unten, dann no_count aufsteigend,
        // score absteigend, open_count aufsteigend, Datum aufsteigend.
        $ranked = $rows->sort(function ($a, $b) {
            return [$a['blocked'], $a['no_count'], -$a['score'], $a['open_count'], $a['starts_at_utc']]
                <=> [$b['blocked'], $b['no_count'], -$b['score'], $b['open_count'], $b['starts_at_utc']];
        })->values();

        return $ranked->map(fn ($row) => [
            ...$row,
            'starts_at_utc' => $row['starts_at_utc']?->toIso8601String(),
        ])->all();
    }

    /**
     * Kein Best Match, solange weniger als die Haelfte der Teilnehmer
     * geantwortet hat (Spec Abschnitt 5).
     */
    public function bestMatchId(Event $event): ?int
    {
        $event->loadMissing(['participants', 'dateOptions.availabilities']);

        $participantCount = $event->participants->count();

        if ($participantCount === 0) {
            return null;
        }

        $answered = $event->participants
            ->filter(fn ($p) => $event->dateOptions
                ->flatMap->availabilities
                ->contains('participant_id', $p->id))
            ->count();

        if ($answered < ceil($participantCount * self::BEST_MATCH_QUORUM)) {
            return null;
        }

        $ranked = $this->rank($event);
        $top = $ranked[0] ?? null;

        if (! $top || $top['blocked'] || $top['score'] === 0) {
            return null;
        }

        return $top['id'];
    }
}
