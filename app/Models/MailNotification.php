<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailNotification extends Model
{
    protected $table = 'mail_notifications';

    protected $fillable = [
        'event_id',
        'recipient_email',
        'type',
        'dedupe_key',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
