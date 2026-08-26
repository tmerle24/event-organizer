<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DateOption extends Model
{
    protected $fillable = [
        'event_id',
        'starts_at_utc',
        'ends_at_utc',
        'day',
        'all_day',
        'sort',
    ];

    protected $casts = [
        'starts_at_utc' => 'datetime',
        'ends_at_utc' => 'datetime',
        'day' => 'date',
        'all_day' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class);
    }
}
