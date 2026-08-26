<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonImmutable;

/**
 * Titel und Text der Link-Vorschau für eine Event-Seite.
 *
 * Wer den Link bekommt, soll ohne Klick wissen, worum es geht und wann es
 * stattfindet. Das ist bewusst anders als der ursprüngliche Stand: dort zeigte
 * jede Event-Seite die generische Marken-Vorschau, damit Vorschau-Bots nichts
 * über das Event verraten. Der Nutzen für eine Einladung wiegt schwerer — der
 * Link selbst ist ohnehin der Schlüssel zum Event.
 *
 * Alles hier läuft serverseitig: Der WhatsApp-Crawler führt kein JavaScript aus.
 */
class SharePreview
{
    public function forEvent(Event $event): array
    {
        return [
            'ogTitle' => $event->title,
            'ogDescription' => $this->description($event),
        ];
    }

    private function description(Event $event): string
    {
        if ($event->status === Event::STATUS_CANCELLED) {
            return __('share.cancelled');
        }

        if ($event->status === Event::STATUS_CLOSED) {
            return __('share.closed');
        }

        $option = $event->decidedOption;

        if ($option) {
            $when = $this->formatOption($option, $event->timezone);

            return $event->location
                ? __('share.at_place', ['when' => $when, 'where' => $event->location])
                : __('share.at', ['when' => $when]);
        }

        if (! $event->hasDates()) {
            return __('share.no_dates');
        }

        $count = $event->dateOptions()->count();

        return $count > 0
            ? trans_choice('share.collecting_with_options', $count, ['count' => $count])
            : __('share.collecting');
    }

    /**
     * Ganztägige Termine nennen kein Uhrzeit — sie sind ein reines Datum
     * und werden nicht in eine Zeitzone umgerechnet.
     */
    private function formatOption($option, string $timezone): string
    {
        $locale = app()->getLocale();

        if ($option->all_day) {
            return CarbonImmutable::parse($option->day ?? $option->starts_at_utc)
                ->locale($locale)
                ->isoFormat('dddd, LL');
        }

        return CarbonImmutable::parse($option->starts_at_utc)
            ->setTimezone($timezone)
            ->locale($locale)
            ->isoFormat('dddd, LL, HH:mm');
    }
}
