<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Availability extends Model
{
    public const YES = 'yes';

    public const NO = 'no';

    public const MAYBE = 'maybe';

    protected $table = 'availabilities';

    protected $fillable = [
        'date_option_id',
        'participant_id',
        'value',
    ];

    public function dateOption()
    {
        return $this->belongsTo(DateOption::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
