<?php

namespace Tests\Unit;

use App\Services\HeuristicExtractor;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Der Fallback muss ohne Framework-Bootstrap funktionieren — er ist die
 * Rueckfallebene, wenn die KI ausfaellt (Spec Abschnitt 7).
 */
class HeuristicExtractorTest extends TestCase
{
    private HeuristicExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new HeuristicExtractor;
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_detects_type_count_and_weekdays(): void
    {
        $result = $this->extractor->extract('Team BBQ mit 8 Personen, Freitag oder Samstag abends');

        $this->assertSame('barbecue', $result['event_type']);
        $this->assertSame(8, $result['participant_count']);
        $this->assertEqualsCanonicalizing(['friday', 'saturday'], $result['preferred_days']);
        $this->assertSame('evening', $result['time_of_day']);
    }

    public function test_relative_months_resolve_to_the_next_future_occurrence(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 10, 15, 12, 0, 0, 'Europe/Berlin'));

        $result = $this->extractor->extract('Grillen im September', 'Europe/Berlin');

        $this->assertSame('2027-09-01', $result['date_range']['from']);
        $this->assertSame('2027-09-30', $result['date_range']['to']);
    }

    public function test_a_month_still_running_starts_today_not_in_the_past(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 15, 12, 0, 0, 'Europe/Berlin'));

        $result = $this->extractor->extract('Grillen im September', 'Europe/Berlin');

        $this->assertSame('2026-09-15', $result['date_range']['from']);
        $this->assertSame('2026-09-30', $result['date_range']['to']);
    }

    public function test_english_input_works_too(): void
    {
        $result = $this->extractor->extract('Team dinner with 6 people next week, tuesday evening');

        $this->assertSame('dinner', $result['event_type']);
        $this->assertSame(6, $result['participant_count']);
        $this->assertContains('tuesday', $result['preferred_days']);
    }

    public function test_without_a_time_frame_no_range_is_invented(): void
    {
        $result = $this->extractor->extract('Kegelabend organisieren');

        $this->assertNull($result['date_range']);
        $this->assertSame('generic', $result['event_type']);
    }

    public function test_a_long_input_is_shortened_to_a_usable_title(): void
    {
        $long = 'Wir wollen ein richtig grosses Sommerfest im Garten feiern, mit Grill, Musik und allem drum und dran';

        $result = $this->extractor->extract($long);

        $this->assertLessThanOrEqual(60, mb_strlen($result['event_name']));
        $this->assertNotEmpty($result['event_name']);
    }
}
