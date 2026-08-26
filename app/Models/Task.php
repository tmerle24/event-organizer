<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    public const OPEN = 'open';

    public const DONE = 'done';

    protected $fillable = [
        'event_id',
        'plan_section_id',
        'title',
        'assignee_participant_id',
        'status',
        'sort',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function section()
    {
        return $this->belongsTo(PlanSection::class, 'plan_section_id');
    }

    public function assignee()
    {
        return $this->belongsTo(Participant::class, 'assignee_participant_id');
    }
}
