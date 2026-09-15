<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versandprotokoll ohne Adressen: Teilnehmer-Mails verweisen auf participant_id,
 * alle anderen (Einladung, Verwaltungslink) nur auf einen HMAC-Hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_notifications', function (Blueprint $table) {
            $table->foreignId('participant_id')->nullable()->after('event_id')
                ->constrained()->cascadeOnDelete();
            $table->string('recipient_hash', 64)->nullable()->after('participant_id');
            // Inhalt der Mail (Termin, Ort, Status) — gleiche Info geht nicht zweimal raus
            $table->string('fingerprint', 64)->nullable()->after('type');
            $table->index(['event_id', 'participant_id', 'type']);
        });

        $key = config('app.key');

        DB::table('mail_notifications')->orderBy('id')->each(function ($row) use ($key) {
            $email = mb_strtolower(trim($row->recipient_email));
            $hash = hash_hmac('sha256', $email, $key);

            DB::table('mail_notifications')->where('id', $row->id)->update([
                'recipient_hash' => $hash,
                'dedupe_key' => str_ireplace($row->recipient_email, $hash, $row->dedupe_key),
                'error' => $row->error
                    ? preg_replace('/[^\s<>"\'@]+@[^\s<>"\'@]+/', '[address]', $row->error)
                    : null,
            ]);
        });

        Schema::table('mail_notifications', function (Blueprint $table) {
            $table->dropColumn('recipient_email');
        });
    }

    public function down(): void
    {
        Schema::table('mail_notifications', function (Blueprint $table) {
            // Adressen lassen sich aus dem Hash nicht zurueckgewinnen
            $table->string('recipient_email')->default('');
            $table->dropIndex(['event_id', 'participant_id', 'type']);
            $table->dropConstrainedForeignId('participant_id');
            $table->dropColumn(['recipient_hash', 'fingerprint']);
        });
    }
};
