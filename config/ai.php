<?php

/**
 * Spec Abschnitt 7 + 11: Die KI ist Beschleuniger, nie Voraussetzung.
 * Ohne API-Key, bei Timeout, Rate-Limit, Schema-Fehler oder ueberschrittenem
 * Tagesbudget faellt die Extraktion auf die Heuristik in HeuristicExtractor
 * zurueck — nie auf eine Fehlerseite.
 */
return [
    'key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    'timeout' => (int) env('AI_TIMEOUT', 8),
    'max_input_chars' => 500,

    // Harter globaler Cutoff pro Tag (Anzahl Extraktionen).
    'daily_budget' => (int) env('AI_DAILY_BUDGET', 500),

    /*
     * Grenzen je IP (Spec Abschnitt 11). Sie gelten nur für den KI-Aufruf,
     * nicht für das Anlegen eines Events: Wer sie überschreitet, bekommt die
     * Heuristik statt eines Fehlers und merkt nichts davon.
     */
    'per_ip_hourly' => (int) env('AI_PER_IP_HOURLY', 5),
    'per_ip_daily' => (int) env('AI_PER_IP_DAILY', 20),
];
