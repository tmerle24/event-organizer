<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Zentraler Container (Spec Abschnitt 9). Saemtliche Zugriffe laufen ueber den
 * Event-Kontext, damit die Autorisierungslogik auf einen Punkt begrenzt bleibt.
 *
 * manage_token ist der Admin-Schluessel und darf NIEMALS ueber eine Public-Route
 * ausgeliefert werden. Public-Responses bauen ihre JSON-Shape deshalb immer
 * explizit auf (siehe PublicEventController), statt das Model zu serialisieren.
 */
class Event extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_DECIDED = 'decided';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_DATES = 'dates';

    public const MODE_LIST = 'list';

    public const MODE_BOTH = 'both';

    protected $fillable = [
        'title',
        'description',
        'location',
        'event_type',
        'planning_template',
        'status',
        'mode',
        'timezone',
        'decided_option_id',
        'participant_count_hint',
        'ai_meta',
        'organizer_email',
        'organizer_name',
        'creator_ip',
        'last_activity_at',
        'delete_after',
        'retention_warned_at',
    ];

    protected $casts = [
        'ai_meta' => 'array',
        'participant_count_hint' => 'integer',
        'last_activity_at' => 'datetime',
        'delete_after' => 'datetime',
        'retention_warned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->public_token ??= self::generateUniqueToken('public_token', 12);
            $event->manage_token ??= self::generateUniqueToken('manage_token', 64);
            $event->last_activity_at ??= now();
            // Retention (Spec Abschnitt 11): 12 Monate nach letzter Aktivitaet.
            $event->delete_after ??= now()->addMonths(12);
        });
    }

    protected static function generateUniqueToken(string $column, int $length): string
    {
        do {
            $token = Str::random($length);
        } while (self::where($column, $token)->exists());

        return $token;
    }

    public function dateOptions()
    {
        return $this->hasMany(DateOption::class)->orderBy('sort')->orderBy('starts_at_utc');
    }

    public function participants()
    {
        return $this->hasMany(Participant::class)->orderBy('id');
    }

    public function planSections()
    {
        return $this->hasMany(PlanSection::class)->orderBy('sort');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class)->orderBy('sort')->orderBy('id');
    }

    public function decidedOption()
    {
        return $this->belongsTo(DateOption::class, 'decided_option_id');
    }

    public function mailNotifications()
    {
        return $this->hasMany(MailNotification::class);
    }

    public function hasDates(): bool
    {
        return in_array($this->mode, [self::MODE_DATES, self::MODE_BOTH], true);
    }

    public function hasList(): bool
    {
        return in_array($this->mode, [self::MODE_LIST, self::MODE_BOTH], true);
    }

    public function isReadOnly(): bool
    {
        return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_CANCELLED], true);
    }

    /**
     * Jede schreibende Teilnehmer-Aktion verlaengert die Retention-Frist.
     */
    public function touchActivity(): void
    {
        $this->forceFill([
            'last_activity_at' => now(),
            'delete_after' => now()->addMonths(12),
            'retention_warned_at' => null,
        ])->saveQuietly();
    }

    /**
     * Spec Abschnitt 3: decided -> planning passiert automatisch bei der ersten
     * Aktion im Planungsbereich, nie ueber einen expliziten Button.
     */
    public function enterPlanningIfNeeded(): void
    {
        if ($this->status === self::STATUS_DECIDED) {
            $this->update(['status' => self::STATUS_PLANNING]);
        }
    }

    public function publicUrl(): string
    {
        return url('/t/'.$this->public_token);
    }

    public function manageUrl(): string
    {
        return url('/e/'.$this->manage_token);
    }
}
