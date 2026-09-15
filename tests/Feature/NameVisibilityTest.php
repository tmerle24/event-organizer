<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Namen erst fuer Eingetragene, Absagen anderer nur als Zahl — serverseitig.
 */
class NameVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Participant $anna;

    private Participant $ben;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'title' => 'Sommerfest',
            'mode' => Event::MODE_BOTH,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_PLANNING,
        ]);

        $option = $this->event->dateOptions()->create([
            'starts_at_utc' => now()->addWeek()->setTime(16, 0),
            'day' => now()->addWeek()->toDateString(),
            'sort' => 0,
        ]);

        $this->anna = $this->event->participants()->create(['display_name' => 'Annabelle', 'token' => str_repeat('a', 32)]);
        $this->ben = $this->event->participants()->create(['display_name' => 'Benedikt', 'token' => str_repeat('b', 32)]);

        Availability::create(['date_option_id' => $option->id, 'participant_id' => $this->anna->id, 'value' => 'yes']);
        Availability::create(['date_option_id' => $option->id, 'participant_id' => $this->ben->id, 'value' => 'no']);

        $this->event->tasks()->create(['title' => 'Salat', 'assignee_participant_id' => $this->ben->id, 'status' => 'open', 'sort' => 0]);
    }

    private function state(?string $token = null)
    {
        return $this->getJson(
            "/t/{$this->event->public_token}/state",
            $token ? ['X-Participant-Token' => $token] : []
        )->assertOk();
    }

    public function test_visitors_see_counts_but_no_names(): void
    {
        $response = $this->state();

        $response->assertJsonPath('event.participants', []);
        $response->assertJsonPath('event.date_options.0.yes_count', 1);
        $response->assertJsonPath('event.date_options.0.no_count', 1);
        $response->assertJsonPath('event.date_options.0.votes', []);
        $response->assertJsonPath('event.tasks.0.assignee_name', null);

        $this->assertStringNotContainsString('Annabelle', $response->getContent());
        $this->assertStringNotContainsString('Benedikt', $response->getContent());
    }

    public function test_the_initial_page_contains_no_names(): void
    {
        // Geraete-Token liegt im LocalStorage, beim ersten Rendern gibt es ihn nie
        $html = $this->get("/t/{$this->event->public_token}")->assertOk()->getContent();

        $this->assertStringNotContainsString('Annabelle', $html);
        $this->assertStringNotContainsString('Benedikt', $html);
    }

    public function test_participants_see_names_and_yes_votes(): void
    {
        $response = $this->state($this->anna->token);

        $response->assertJsonPath('event.participants.1.display_name', 'Benedikt');
        $response->assertJsonPath('event.tasks.0.assignee_name', 'Benedikt');
        $response->assertJsonPath("event.date_options.0.votes.{$this->anna->id}", 'yes');
    }

    public function test_declines_of_others_are_only_a_count(): void
    {
        $response = $this->state($this->anna->token);

        $this->assertArrayNotHasKey($this->ben->id, $response->json('event.date_options.0.votes'));
        $response->assertJsonPath('event.date_options.0.no_count', 1);
    }

    public function test_participants_still_see_their_own_decline(): void
    {
        $this->state($this->ben->token)
            ->assertJsonPath("event.date_options.0.votes.{$this->ben->id}", 'no');
    }

    public function test_the_organizer_sees_every_answer(): void
    {
        $this->getJson("/e/{$this->event->manage_token}/data")
            ->assertOk()
            ->assertJsonPath("event.date_options.0.votes.{$this->ben->id}", 'no')
            ->assertJsonPath('event.participants.1.display_name', 'Benedikt');
    }

    public function test_joining_reveals_the_names(): void
    {
        $this->postJson("/t/{$this->event->public_token}/join", [
            'display_name' => 'Carla',
            'token' => str_repeat('c', 32),
        ])->assertCreated()->assertJsonPath('event.participants.0.display_name', 'Annabelle');
    }

    public function test_leaving_hides_the_names_again(): void
    {
        $response = $this->postJson("/t/{$this->event->public_token}/leave", ['token' => $this->anna->token])->assertOk();

        $this->assertStringNotContainsString('Benedikt', $response->getContent());
    }
}
