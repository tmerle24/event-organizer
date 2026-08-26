<?php

namespace Tests\Unit;

use App\Services\DateOptionSuggester;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateOptionSuggesterTest extends TestCase
{
    private DateOptionSuggester $suggester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suggester = new DateOptionSuggester;
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 1, 8, 0, 0, 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_caps_the_number_of_options(): void
    {
        $options = $this->suggester->suggest([
            'date_range' => ['from' => '2026-09-01', 'to' => '2026-09-30'],
            'time_of_day' => 'evening',
            'preferred_days' => [],
        ], 'Europe/Berlin');

        $this->assertCount(DateOptionSuggester::MAX_OPTIONS, $options);
    }

    public function test_it_only_uses_the_preferred_weekdays(): void
    {
        $options = $this->suggester->suggest([
            'date_range' => ['from' => '2026-09-01', 'to' => '2026-09-30'],
            'time_of_day' => 'evening',
            'preferred_days' => ['saturday'],
        ], 'Europe/Berlin');

        foreach ($options as $option) {
            $this->assertSame(
                CarbonImmutable::SATURDAY,
                $option['starts_at_utc']->setTimezone('Europe/Berlin')->dayOfWeek
            );
        }
    }

    public function test_it_stores_utc_and_keeps_the_local_time(): void
    {
        $options = $this->suggester->suggest([
            'date_range' => ['from' => '2026-09-04', 'to' => '2026-09-04'],
            'time_of_day' => 'evening',
            'preferred_days' => [],
        ], 'Europe/Berlin');

        $this->assertSame('UTC', $options[0]['starts_at_utc']->timezone->getName());
        $this->assertSame('18:00', $options[0]['starts_at_utc']->setTimezone('Europe/Berlin')->format('H:i'));
    }

    public function test_it_never_suggests_a_date_in_the_past(): void
    {
        $options = $this->suggester->suggest([
            'date_range' => ['from' => '2026-08-01', 'to' => '2026-09-05'],
            'time_of_day' => 'evening',
            'preferred_days' => [],
        ], 'Europe/Berlin');

        foreach ($options as $option) {
            $this->assertTrue($option['starts_at_utc']->greaterThan(CarbonImmutable::now('UTC')));
        }
    }

    public function test_without_a_range_there_are_no_suggestions(): void
    {
        $this->assertSame([], $this->suggester->suggest(['date_range' => null], 'Europe/Berlin'));
    }

    public function test_it_falls_back_to_the_range_when_no_weekday_matches(): void
    {
        // Mo 2026-09-07 bis Mi 2026-09-09 enthaelt keinen Samstag.
        $options = $this->suggester->suggest([
            'date_range' => ['from' => '2026-09-07', 'to' => '2026-09-09'],
            'time_of_day' => 'evening',
            'preferred_days' => ['saturday'],
        ], 'Europe/Berlin');

        $this->assertNotEmpty($options, 'Lieber die Tage des Zeitraums als gar keine Vorschlaege.');
    }
}
