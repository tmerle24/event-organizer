<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonImmutable;

/**
 * ICS-Download nach der Terminbestaetigung (Spec Abschnitt 12: "bewusst hinter
 * der Cut-Line, aber billig"). Kein Kalendersync — nur eine Datei.
 */
class IcsBuilder
{
    public function build(Event $event): string
    {
        $option = $event->decidedOption;

        if (! $option) {
            throw new \RuntimeException('Event has no decided date option.');
        }

        $uid = 'orgdate-'.$event->public_token.'-'.$option->id.'@'.parse_url(config('app.url'), PHP_URL_HOST);
        $stamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');

        if ($option->all_day) {
            $day = CarbonImmutable::parse($option->day ?? $option->starts_at_utc);
            $start = 'DTSTART;VALUE=DATE:'.$day->format('Ymd');
            $end = 'DTEND;VALUE=DATE:'.$day->addDay()->format('Ymd');
        } else {
            $startsAt = CarbonImmutable::parse($option->starts_at_utc)->setTimezone('UTC');
            $endsAt = $option->ends_at_utc
                ? CarbonImmutable::parse($option->ends_at_utc)->setTimezone('UTC')
                : $startsAt->addHours(3);

            $start = 'DTSTART:'.$startsAt->format('Ymd\THis\Z');
            $end = 'DTEND:'.$endsAt->format('Ymd\THis\Z');
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('app.name').'//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            $start,
            $end,
            'SUMMARY:'.$this->escape($event->title),
            'DESCRIPTION:'.$this->escape(trim(($event->description ?? '')."\n".$event->publicUrl())),
            'URL:'.$event->publicUrl(),
        ];

        if ($event->location) {
            $lines[] = 'LOCATION:'.$this->escape($event->location);
        }

        if ($event->status === Event::STATUS_CANCELLED) {
            $lines[] = 'STATUS:CANCELLED';
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // RFC 5545 verlangt CRLF als Zeilentrenner — Outlook ist da streng.
        return implode("\r\n", $lines)."\r\n";
    }

    public function filename(Event $event): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $event->title);

        return trim(mb_strtolower($slug), '-').'.ics';
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            $value
        );
    }
}
