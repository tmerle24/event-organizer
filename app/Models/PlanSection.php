<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanSection extends Model
{
    protected $fillable = [
        'event_id',
        'key',
        'title',
        'sort',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class)->orderBy('sort')->orderBy('id');
    }
}
