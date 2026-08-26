<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Event;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deckt die Ranking-Regeln aus Spec Abschnitt 5 ab.
 */
class RankingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'title' => 'Ranking',
            'mode' => Event::MODE_DATES,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_COLLECTING,
        ]);
    }

    private function option(int $offset)
    {
        return $this->event->dateOptions()->create([
            'starts_at_utc' => now()->addDays($offset)->setTime(16, 0),
            'day' => now()->addDays($offset)->toDateString(),
            'sort' => $offset,
        ]);
    }

    private function participant(string $name, bool $required = false)
    {
        return $this->event->participants()->create([
            'display_name' => $name,
            'token' => str_pad($name, 32, 'x'),
            'is_required' => $required,
        ]);
    }

    private function answer($option, $participant, string $value): void
    {
        Availability::create([
            'date_option_id' => $option->id,
            'participant_id' => $participant->id,
            'value' => $value,
        ]);
    }

    public function test_score_is_two_per_yes_plus_one_per_maybe(): void
    {
        $option = $this->option(1);
        $this->answer($option, $this->participant('a'), 'yes');
        $this->answer($option, $this->participant('b'), 'yes');
        $this->answer($option, $this->participant('c'), 'maybe');

        $row = app(RankingService::class)->rank($this->event->fresh())[0];

        $this->assertSame(5, $row['score']);
        $this->assertSame(2, $row['yes_count']);
        $this->assertSame(1, $row['maybe_count']);
        $this->assertSame(0, $row['open_count']);
    }

    public function test_options_with_fewer_nos_rank_higher_even_with_a_lower_score(): void
    {
        $clean = $this->option(1);
        $popular = $this->option(2);

        $people = collect(range(1, 4))->map(fn ($i) => $this->participant("p{$i}"));

        // "popular" hat mehr Ja-Stimmen, aber auch eine Absage.
        $this->answer($popular, $people[0], 'yes');
        $this->answer($popular, $people[1], 'yes');
        $this->answer($popular, $people[2], 'yes');
        $this->answer($popular, $people[3], 'no');

        $this->answer($clean, $people[0], 'yes');
        $this->answer($clean, $people[1], 'yes');

        $ranked = app(RankingService::class)->rank($this->event->fresh());

        $this->assertSame($clean->id, $ranked[0]['id'], 'no_count schlaegt score.');
    }

    public function test_blocked_options_sink_to_the_bottom_but_stay_visible(): void
    {
        $blocked = $this->option(1);
        $fine = $this->option(2);

        $boss = $this->participant('boss', required: true);
        $other = $this->participant('other');

        $this->answer($blocked, $boss, 'no');
        $this->answer($blocked, $other, 'yes');
        $this->answer($fine, $other, 'maybe');

        $ranked = app(RankingService::class)->rank($this->event->fresh());

        $this->assertCount(2, $ranked, 'Blockierte Optionen werden nicht ausgeblendet.');
        $this->assertSame($fine->id, $ranked[0]['id']);
        $this->assertTrue($ranked[1]['blocked']);
    }

    public function test_no_best_match_before_half_the_participants_answered(): void
    {
        $option = $this->option(1);

        $people = collect(range(1, 4))->map(fn ($i) => $this->participant("q{$i}"));
        $this->answer($option, $people[0], 'yes');

        $this->assertNull(app(RankingService::class)->bestMatchId($this->event->fresh()));

        $this->answer($option, $people[1], 'yes');

        $this->assertSame($option->id, app(RankingService::class)->bestMatchId($this->event->fresh()));
    }

    public function test_a_blocked_top_option_is_never_the_best_match(): void
    {
        $option = $this->option(1);
        $boss = $this->participant('chief', required: true);
        $other = $this->participant('mate');

        $this->answer($option, $boss, 'no');
        $this->answer($option, $other, 'yes');

        $this->assertNull(app(RankingService::class)->bestMatchId($this->event->fresh()));
    }
}
