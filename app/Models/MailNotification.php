<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailNotification extends Model
{
    protected $table = 'mail_notifications';

    protected $fillable = [
        'event_id',
        'participant_id',
        'recipient_hash',
        'type',
        'fingerprint',
        'dedupe_key',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
