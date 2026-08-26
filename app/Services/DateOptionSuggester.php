<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonImmutable;

/**
 * Erzeugt aus dem extrahierten Zeitraum konkrete Terminoptionen (Spec
 * Abschnitt 4, Schritt 2). Ohne Zeitraum entstehen keine Optionen — dann
 * fragt das UI genau einmal nach ("When roughly?").
 */
class DateOptionSuggester
{
    private const TIMES = [
        'morning' => '09:00',
        'midday' => '12:00',
        'afternoon' => '15:00',
        'evening' => '18:00',
    ];

    private const WEEKDAY_NUMBERS = [
        'monday' => CarbonImmutable::MONDAY,
        'tuesday' => CarbonImmutable::TUESDAY,
        'wednesday' => CarbonImmutable::WEDNESDAY,
        'thursday' => CarbonImmutable::THURSDAY,
        'friday' => CarbonImmutable::FRIDAY,
        'saturday' => CarbonImmutable::SATURDAY,
        'sunday' => CarbonImmutable::SUNDAY,
    ];

    public const MAX_OPTIONS = 6;

    /**
     * @return list<array{starts_at_utc: CarbonImmutable, ends_at_utc: null, day: string, all_day: false, sort: int}>
     */
    public function suggest(array $extraction, string $timezone): array
    {
        $range = $extraction['date_range'] ?? null;

        if (! $range) {
            return [];
        }

        $time = self::TIMES[$extraction['time_of_day'] ?? 'evening'] ?? '18:00';
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $from = CarbonImmutable::parse($range['from'], $timezone)->startOfDay();
        $to = CarbonImmutable::parse($range['to'], $timezone)->endOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($from->lessThan($today)) {
            $from = $today;
        }

        $wanted = array_values(array_filter(array_map(
            fn ($day) => self::WEEKDAY_NUMBERS[$day] ?? null,
            $extraction['preferred_days'] ?? []
        ), fn ($n) => $n !== null));

        $options = [];
        $cursor = $from;
        $sort = 0;

        while ($cursor->lessThanOrEqualTo($to) && count($options) < self::MAX_OPTIONS) {
            if (! $wanted || in_array($cursor->dayOfWeek, $wanted, true)) {
                $start = $cursor->setTime($hour, $minute);

                if ($start->greaterThan(CarbonImmutable::now($timezone))) {
                    $options[] = [
                        'starts_at_utc' => $start->setTimezone('UTC'),
                        'ends_at_utc' => null,
                        'day' => $start->toDateString(),
                        'all_day' => false,
                        'sort' => $sort++,
                    ];
                }
            }

            $cursor = $cursor->addDay();
        }

        // Kein Wochentag getroffen (z.B. "Fr/Sa" in einem 3-Tages-Fenster):
        // lieber die ersten Tage des Zeitraums anbieten als gar nichts.
        if (! $options && $wanted) {
            return $this->suggest(['date_range' => $range, 'time_of_day' => $extraction['time_of_day'] ?? 'evening'], $timezone);
        }

        return $options;
    }

    public function applyTo(Event $event, array $extraction): void
    {
        foreach ($this->suggest($extraction, $event->timezone) as $option) {
            $event->dateOptions()->create($option);
        }
    }
}
