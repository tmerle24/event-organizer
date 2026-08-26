<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = [
        'event_id',
        'display_name',
        'email',
        'token',
        'is_required',
        'is_organizer',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_organizer' => 'boolean',
    ];

    protected $hidden = ['token'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assignee_participant_id');
    }
}
