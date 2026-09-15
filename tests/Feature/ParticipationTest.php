<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): Event
    {
        $event = Event::create([
            'title' => 'Test BBQ',
            'mode' => Event::MODE_BOTH,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_COLLECTING,
        ]);

        foreach ([1, 2, 3] as $offset) {
            $event->dateOptions()->create([
                'starts_at_utc' => now()->addDays($offset)->setTime(16, 0),
                'day' => now()->addDays($offset)->toDateString(),
                'sort' => $offset,
            ]);
        }

        return $event;
    }

    public function test_a_visitor_becomes_a_participant_on_the_first_answer(): void
    {
        $event = $this->makeEvent();

        $this->postJson("/t/{$event->public_token}/join", [
            'display_name' => 'Anna',
            'token' => str_repeat('a', 32),
        ])->assertCreated()
            ->assertJsonPath('event.me.display_name', 'Anna');

        $this->assertSame(1, $event->participants()->count());
    }

    public function test_the_public_response_never_contains_the_manage_token(): void
    {
        $event = $this->makeEvent();

        $response = $this->getJson("/t/{$event->public_token}/state");

        $response->assertOk();
        $this->assertStringNotContainsString($event->manage_token, $response->getContent());
        $this->assertArrayNotHasKey('manage_token', $response->json('event'));
    }

    public function test_a_participant_can_set_and_change_availability(): void
    {
        $event = $this->makeEvent();
        $token = str_repeat('b', 32);
        $option = $event->dateOptions()->first();

        $this->postJson("/t/{$event->public_token}/join", ['display_name' => 'Ben', 'token' => $token]);

        $this->postJson("/t/{$event->public_token}/availability", [
            'token' => $token,
            'answers' => [$option->id => 'yes'],
        ])->assertOk();

        $this->assertSame('yes', Availability::firstOrFail()->value);

        $this->postJson("/t/{$event->public_token}/availability", [
            'token' => $token,
            'answers' => [$option->id => 'maybe'],
        ])->assertOk();

        $this->assertSame(1, Availability::count());
        $this->assertSame('maybe', Availability::firstOrFail()->value);
    }

    public function test_unanswered_options_stay_open_and_are_never_counted_as_no(): void
    {
        $event = $this->makeEvent();
        $token = str_repeat('c', 32);
        $first = $event->dateOptions()->first();

        $this->postJson("/t/{$event->public_token}/join", ['display_name' => 'Cara', 'token' => $token]);
        $this->postJson("/t/{$event->public_token}/availability", [
            'token' => $token,
            'answers' => [$first->id => 'yes'],
        ]);

        $options = collect($this->getJson("/t/{$event->public_token}/state")->json('event.date_options'))
            ->keyBy('id');

        $others = $event->dateOptions()->where('id', '!=', $first->id)->pluck('id');

        foreach ($others as $id) {
            $this->assertSame(0, $options[$id]['no_count']);
            $this->assertSame(1, $options[$id]['open_count']);
        }
    }

    public function test_a_participant_can_remove_their_own_participation(): void
    {
        $event = $this->makeEvent();
        $token = str_repeat('d', 32);
        $option = $event->dateOptions()->first();

        $this->postJson("/t/{$event->public_token}/join", ['display_name' => 'Dana', 'token' => $token]);
        $this->postJson("/t/{$event->public_token}/availability", [
            'token' => $token,
            'answers' => [$option->id => 'no'],
        ]);

        $this->postJson("/t/{$event->public_token}/leave", ['token' => $token])->assertOk();

        $this->assertSame(0, $event->participants()->count());
        $this->assertSame(0, Availability::count());
    }

    public function test_a_cancelled_event_rejects_new_answers(): void
    {
        $event = $this->makeEvent();
        $event->update(['status' => Event::STATUS_CANCELLED]);

        $this->postJson("/t/{$event->public_token}/join", [
            'display_name' => 'Eve',
            'token' => str_repeat('e', 32),
        ])->assertStatus(422);
    }

    public function test_a_public_token_does_not_open_the_manage_area(): void
    {
        $event = $this->makeEvent();

        $this->get("/e/{$event->public_token}")->assertNotFound();
    }
}
