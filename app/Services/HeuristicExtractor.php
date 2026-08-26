<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Fallback-Extraktion ohne KI (Spec Abschnitt 7, "Fallback").
 *
 * Bewusst simpel: Monatsnamen, Wochentage, Tageszeit, Teilnehmerzahl und
 * Event-Typ per Wortliste (DE/EN). Liefert exakt dasselbe Schema wie der
 * AiExtractor, damit der Aufrufer keinen Unterschied kennen muss.
 */
class HeuristicExtractor
{
    private const MONTHS = [
        1 => ['januar', 'january', 'jan'],
        2 => ['februar', 'february', 'feb'],
        3 => ['maerz', 'märz', 'march', 'mar', 'mrz'],
        4 => ['april', 'apr'],
        5 => ['mai', 'may'],
        6 => ['juni', 'june', 'jun'],
        7 => ['juli', 'july', 'jul'],
        8 => ['august', 'aug'],
        9 => ['september', 'sept', 'sep'],
        10 => ['oktober', 'october', 'okt', 'oct'],
        11 => ['november', 'nov'],
        12 => ['dezember', 'december', 'dez', 'dec'],
    ];

    private const WEEKDAYS = [
        'monday' => ['montag', 'monday', 'mo ', 'mon'],
        'tuesday' => ['dienstag', 'tuesday', 'di ', 'tue'],
        'wednesday' => ['mittwoch', 'wednesday', 'mi ', 'wed'],
        'thursday' => ['donnerstag', 'thursday', 'do ', 'thu'],
        'friday' => ['freitag', 'friday', 'fr ', 'fri'],
        'saturday' => ['samstag', 'sonnabend', 'saturday', 'sa ', 'sat'],
        'sunday' => ['sonntag', 'sunday', 'so ', 'sun'],
    ];

    private const TYPES = [
        'barbecue' => ['grill', 'bbq', 'barbecue', 'barbeque', 'grillen', 'grillabend'],
        'dinner' => ['dinner', 'abendessen', 'essen', 'brunch', 'fruehstueck', 'frühstück', 'restaurant', 'kochen'],
        'party' => ['party', 'feier', 'geburtstag', 'birthday', 'jubilaeum', 'jubiläum', 'hochzeit', 'wedding', 'fest'],
        'trip' => ['reise', 'trip', 'urlaub', 'ausflug', 'wochenende weg', 'wandern', 'hiking', 'camping', 'staedtetrip', 'städtetrip'],
        'meeting' => ['meeting', 'besprechung', 'termin', 'workshop', 'call', 'jour fixe', 'retro', 'sitzung'],
        'sports' => ['training', 'fussball', 'fußball', 'sport', 'lauf', 'turnier', 'match', 'spiel'],
    ];

    public function extract(string $input, string $timezone = 'Europe/Berlin'): array
    {
        $text = ' '.mb_strtolower(trim($input)).' ';
        $now = CarbonImmutable::now($timezone);

        $type = $this->detectType($text);

        return [
            'event_name' => $this->buildName($input, $type),
            'event_type' => $type,
            'participant_count' => $this->detectParticipantCount($text),
            'date_range' => $this->detectDateRange($text, $now),
            'preferred_days' => $this->detectWeekdays($text),
            'time_of_day' => $this->detectTimeOfDay($text, $type),
            'planning_template' => config('planning.type_to_template.'.$type, 'generic'),
            'confidence' => [
                'event_name' => 'low',
                'participant_count' => $this->detectParticipantCount($text) ? 'medium' : 'low',
                'date_range' => $this->detectDateRange($text, $now) ? 'medium' : 'low',
            ],
            'source' => 'heuristic',
        ];
    }

    private function detectType(string $text): string
    {
        foreach (self::TYPES as $type => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $type;
                }
            }
        }

        return 'generic';
    }

    /**
     * Der Freitext ist bei kurzer Eingabe der beste Titel. Bei laengeren
     * Saetzen wird auf den ersten Teilsatz gekuerzt, damit die Ueberschrift
     * nicht die halbe Eingabe wiederholt.
     */
    private function buildName(string $input, string $type): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $input));

        if (mb_strlen($clean) <= 60) {
            return mb_convert_case(mb_substr($clean, 0, 1), MB_CASE_UPPER).mb_substr($clean, 1);
        }

        $firstClause = preg_split('/[,.;:]|\bam\b|\bim\b|\bin\b/u', $clean)[0] ?? $clean;
        $firstClause = trim($firstClause);

        if (mb_strlen($firstClause) >= 3 && mb_strlen($firstClause) <= 60) {
            return mb_convert_case(mb_substr($firstClause, 0, 1), MB_CASE_UPPER).mb_substr($firstClause, 1);
        }

        return mb_substr($clean, 0, 57).'...';
    }

    private function detectParticipantCount(string $text): ?int
    {
        if (preg_match('/(\d{1,3})\s*(personen|leute|teilnehmer|gaeste|gäste|people|persons|guests|mann|kollegen|freunde)/u', $text, $m)) {
            $n = (int) $m[1];

            return $n > 0 && $n <= 500 ? $n : null;
        }

        if (preg_match('/(?:zu|wir sind|mit)\s+(\d{1,3})\b/u', $text, $m)) {
            $n = (int) $m[1];

            return $n > 1 && $n <= 500 ? $n : null;
        }

        return null;
    }

    /**
     * Spec Abschnitt 7: relative Zeitangaben loesen immer auf das naechste
     * zukuenftige Vorkommen auf. "im September" im Oktober meint September
     * des Folgejahres.
     */
    private function detectDateRange(string $text, CarbonImmutable $now): ?array
    {
        foreach (self::MONTHS as $number => $names) {
            foreach ($names as $name) {
                if (! str_contains($text, $name)) {
                    continue;
                }

                $year = $now->year;
                if ($number < $now->month) {
                    $year++;
                }

                $start = CarbonImmutable::create($year, $number, 1, 0, 0, 0, $now->timezone);
                $from = $start->lessThan($now) ? $now->startOfDay() : $start;

                return [
                    'from' => $from->toDateString(),
                    'to' => $start->endOfMonth()->toDateString(),
                ];
            }
        }

        if (preg_match('/(naechste|nächste|next)\s+(woche|week)/u', $text)) {
            return [
                'from' => $now->addWeek()->startOfWeek()->toDateString(),
                'to' => $now->addWeek()->endOfWeek()->toDateString(),
            ];
        }

        if (preg_match('/(diese|this)\s+(woche|week)/u', $text)) {
            return [
                'from' => $now->toDateString(),
                'to' => $now->endOfWeek()->toDateString(),
            ];
        }

        if (preg_match('/(naechsten|nächsten|next)\s+(monat|month)/u', $text)) {
            return [
                'from' => $now->addMonthNoOverflow()->startOfMonth()->toDateString(),
                'to' => $now->addMonthNoOverflow()->endOfMonth()->toDateString(),
            ];
        }

        if (preg_match('/(wochenende|weekend)/u', $text)) {
            $saturday = $now->next(CarbonImmutable::SATURDAY);

            return [
                'from' => $saturday->toDateString(),
                'to' => $saturday->addDay()->toDateString(),
            ];
        }

        return null;
    }

    /** @return list<string> */
    private function detectWeekdays(string $text): array
    {
        $found = [];

        foreach (self::WEEKDAYS as $day => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    $found[] = $day;
                    break;
                }
            }
        }

        if (! $found && preg_match('/(wochenende|weekend)/u', $text)) {
            $found = ['saturday', 'sunday'];
        }

        return array_values(array_unique($found));
    }

    private function detectTimeOfDay(string $text, string $type): string
    {
        if (preg_match('/(morgens|vormittag|morning|fruehstueck|frühstück|brunch)/u', $text)) {
            return 'morning';
        }

        if (preg_match('/(mittags|mittag|lunch|noon)/u', $text)) {
            return 'midday';
        }

        if (preg_match('/(nachmittag|afternoon|kaffee)/u', $text)) {
            return 'afternoon';
        }

        if (preg_match('/(abends|abend|evening|night|nachts|dinner)/u', $text)) {
            return 'evening';
        }

        return $type === 'meeting' ? 'midday' : 'evening';
    }
}
