<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Participant;
use App\Services\PlanBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use RefreshDatabase;

    private function decidedEvent(): Event
    {
        $event = Event::create([
            'title' => 'Grillfest',
            'mode' => Event::MODE_BOTH,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_DECIDED,
            'planning_template' => 'barbecue',
        ]);

        app(PlanBuilder::class)->buildSections($event);

        return $event;
    }

    public function test_sections_come_from_the_planning_template(): void
    {
        $event = $this->decidedEvent();

        $this->assertEqualsCanonicalizing(
            ['food', 'drinks', 'equipment', 'organization'],
            $event->planSections()->pluck('key')->all()
        );
    }

    public function test_the_first_task_moves_the_event_into_planning_without_a_button(): void
    {
        $event = $this->decidedEvent();

        $this->postJson("/e/{$event->manage_token}/tasks", ['title' => 'Kohle kaufen'])->assertCreated();

        $this->assertSame(Event::STATUS_PLANNING, $event->fresh()->status);
    }

    public function test_suggestions_disappear_once_they_are_adopted(): void
    {
        $event = $this->decidedEvent();
        $section = $event->planSections()->where('key', 'drinks')->firstOrFail();

        $before = $this->getJson("/e/{$event->manage_token}/data")->json('event.task_suggestions.drinks');
        $this->assertNotEmpty($before);

        $this->postJson("/e/{$event->manage_token}/tasks/adopt", [
            'titles' => [$before[0]],
            'plan_section_id' => $section->id,
        ])->assertCreated();

        $after = $this->getJson("/e/{$event->manage_token}/data")->json('event.task_suggestions.drinks') ?? [];

        $this->assertNotContains($before[0], $after);
        $this->assertSame(1, $event->tasks()->count());
    }

    public function test_a_participant_can_claim_and_release_a_free_task(): void
    {
        $event = $this->decidedEvent();
        $token = str_repeat('m', 32);

        $this->postJson("/t/{$event->public_token}/join", ['display_name' => 'Mia', 'token' => $token]);
        $me = Participant::firstOrFail();

        $task = $event->tasks()->create(['title' => 'Salat mitbringen', 'sort' => 1]);

        $this->patchJson("/t/{$event->public_token}/tasks/{$task->id}", [
            'token' => $token,
            'assignee_participant_id' => $me->id,
        ])->assertOk();

        $this->assertSame($me->id, $task->fresh()->assignee_participant_id);

        $this->patchJson("/t/{$event->public_token}/tasks/{$task->id}", [
            'token' => $token,
            'assignee_participant_id' => null,
        ])->assertOk();

        $this->assertNull($task->fresh()->assignee_participant_id);
    }

    public function test_a_participant_cannot_take_over_someone_elses_task(): void
    {
        $event = $this->decidedEvent();

        $owner = $event->participants()->create(['display_name' => 'Owner', 'token' => str_repeat('o', 32)]);
        $other = $event->participants()->create(['display_name' => 'Other', 'token' => str_repeat('p', 32)]);

        $task = $event->tasks()->create([
            'title' => 'Grill anheizen',
            'assignee_participant_id' => $owner->id,
            'sort' => 1,
        ]);

        $this->patchJson("/t/{$event->public_token}/tasks/{$task->id}", [
            'token' => $other->token,
            'assignee_participant_id' => $other->id,
        ])->assertForbidden();

        $this->assertSame($owner->id, $task->fresh()->assignee_participant_id);
    }

    public function test_a_participant_cannot_delete_a_task_they_do_not_own(): void
    {
        $event = $this->decidedEvent();
        $other = $event->participants()->create(['display_name' => 'Other', 'token' => str_repeat('q', 32)]);
        $task = $event->tasks()->create(['title' => 'Deko', 'sort' => 1]);

        $this->call('DELETE', "/t/{$event->public_token}/tasks/{$task->id}", ['token' => $other->token])
            ->assertForbidden();

        $this->assertSame(1, $event->tasks()->count());
    }

    public function test_deleting_a_section_keeps_its_tasks(): void
    {
        $event = $this->decidedEvent();
        $section = $event->planSections()->first();
        $event->tasks()->create(['title' => 'Bleibt erhalten', 'plan_section_id' => $section->id, 'sort' => 1]);

        $this->deleteJson("/e/{$event->manage_token}/sections/{$section->id}")->assertOk();

        $this->assertSame(1, $event->tasks()->count());
        $this->assertNull($event->tasks()->first()->plan_section_id);
    }

    public function test_the_organizer_can_assign_any_participant(): void
    {
        $event = $this->decidedEvent();
        $someone = $event->participants()->create(['display_name' => 'Ted', 'token' => str_repeat('t', 32)]);
        $task = $event->tasks()->create(['title' => 'Musik', 'sort' => 1]);

        $this->patchJson("/e/{$event->manage_token}/tasks/{$task->id}", [
            'assignee_participant_id' => $someone->id,
        ])->assertOk();

        $this->assertSame($someone->id, $task->fresh()->assignee_participant_id);
    }

    public function test_assignment_to_a_foreign_participant_is_rejected(): void
    {
        $event = $this->decidedEvent();

        $stranger = Event::create(['title' => 'Anderes', 'timezone' => 'Europe/Berlin'])
            ->participants()->create(['display_name' => 'Fremd', 'token' => str_repeat('f', 32)]);

        $task = $event->tasks()->create(['title' => 'Irgendwas', 'sort' => 1]);

        $this->patchJson("/e/{$event->manage_token}/tasks/{$task->id}", [
            'assignee_participant_id' => $stranger->id,
        ])->assertStatus(422);
    }
}
