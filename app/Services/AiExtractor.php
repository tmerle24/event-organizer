<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Extraktion per Claude API mit erzwungenem Schema (Tool-Use als Structured
 * Output). Jeder Fehlerpfad — kein Key, Timeout, Rate Limit, Budget
 * ueberschritten, ungueltiges Schema — endet im HeuristicExtractor.
 *
 * Spec Abschnitt 7: "Ein Ausfall des LLM-Providers darf die Produkterstellung
 * nicht blockieren."
 */
class AiExtractor
{
    public function __construct(private readonly HeuristicExtractor $heuristic) {}

    public function extract(string $input, string $timezone = 'Europe/Berlin', ?string $ip = null): array
    {
        $input = mb_substr(trim($input), 0, (int) config('ai.max_input_chars'));

        if (! config('ai.key') || ! $this->withinIpLimit($ip) || ! $this->consumeBudget()) {
            return $this->heuristic->extract($input, $timezone);
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('ai.key'),
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout((int) config('ai.timeout'))
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('ai.model'),
                    'max_tokens' => 1024,
                    'tool_choice' => ['type' => 'tool', 'name' => 'create_event'],
                    'tools' => [$this->toolSchema()],
                    'system' => $this->systemPrompt($timezone),
                    'messages' => [
                        ['role' => 'user', 'content' => $input],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('AI extraction failed', ['status' => $response->status()]);

                return $this->heuristic->extract($input, $timezone);
            }

            $payload = collect($response->json('content', []))
                ->firstWhere('type', 'tool_use')['input'] ?? null;

            if (! is_array($payload)) {
                return $this->heuristic->extract($input, $timezone);
            }

            return $this->normalize($payload, $input, $timezone);
        } catch (\Throwable $e) {
            Log::warning('AI extraction threw', ['message' => $e->getMessage()]);

            return $this->heuristic->extract($input, $timezone);
        }
    }

    /**
     * Grenzen je IP (Spec Abschnitt 11): 5 pro Stunde, 20 pro Tag.
     *
     * Bewusst hier und nicht als Drosselung auf der Route: Eine Route-Grenze
     * antwortet mit 429, das Anlegen des Events wuerde also scheitern. Hier
     * faellt nur der KI-Aufruf weg, die Heuristik uebernimmt, und die Person
     * merkt nichts davon.
     */
    private function withinIpLimit(?string $ip): bool
    {
        $ip ??= request()->ip();

        if (! $ip) {
            return true;
        }

        $hourly = (int) config('ai.per_ip_hourly');
        $daily = (int) config('ai.per_ip_daily');

        $hourKey = 'ai:ip:hour:'.$ip;
        $dayKey = 'ai:ip:day:'.$ip;

        // Erst pruefen, dann beide zaehlen: sonst verbraucht eine bereits am
        // Tageslimit gescheiterte Anfrage noch einen Platz im Stundenfenster.
        if (RateLimiter::tooManyAttempts($hourKey, $hourly)
            || RateLimiter::tooManyAttempts($dayKey, $daily)) {
            return false;
        }

        RateLimiter::hit($hourKey, 3600);
        RateLimiter::hit($dayKey, 86400);

        return true;
    }

    /**
     * Globales Tagesbudget als harter Cutoff (Spec Abschnitt 11). Bei
     * Ueberschreitung greift der Fallback statt eines Fehlers.
     */
    private function consumeBudget(): bool
    {
        $budget = (int) config('ai.daily_budget');

        if ($budget <= 0) {
            return true;
        }

        $key = 'ai:budget:'.now()->toDateString();
        $used = (int) Cache::get($key, 0);

        if ($used >= $budget) {
            return false;
        }

        Cache::put($key, $used + 1, now()->endOfDay());

        return true;
    }

    private function systemPrompt(string $timezone): string
    {
        $today = CarbonImmutable::now($timezone)->toDateString();

        return <<<TXT
        You extract structured event data from a short free-text description.
        Today is {$today} (timezone {$timezone}).

        Rules:
        - Relative time expressions always resolve to the NEXT FUTURE occurrence.
          "in September" written in October means September of the following year.
        - date_range must be a plausible window to poll for dates, at most 8 weeks wide.
        - If no time frame is mentioned at all, omit date_range entirely. Do not invent one.
        - event_name is a short human label (max 60 chars) in the language of the input.
        - Set confidence "low" for anything you had to guess.
        TXT;
    }

    private function toolSchema(): array
    {
        return [
            'name' => 'create_event',
            'description' => 'Create the structured event draft.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'event_name' => ['type' => 'string'],
                    'event_type' => [
                        'type' => 'string',
                        'enum' => ['barbecue', 'dinner', 'party', 'trip', 'meeting', 'sports', 'generic'],
                    ],
                    'participant_count' => ['type' => 'integer'],
                    'date_range' => [
                        'type' => 'object',
                        'properties' => [
                            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        ],
                        'required' => ['from', 'to'],
                    ],
                    'preferred_days' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        ],
                    ],
                    'time_of_day' => [
                        'type' => 'string',
                        'enum' => ['morning', 'midday', 'afternoon', 'evening'],
                    ],
                    'planning_template' => [
                        'type' => 'string',
                        'enum' => ['barbecue', 'dinner', 'party', 'trip', 'meeting', 'generic'],
                    ],
                    'confidence' => [
                        'type' => 'object',
                        'properties' => [
                            'event_name' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'participant_count' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'date_range' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        ],
                    ],
                ],
                'required' => ['event_name', 'event_type'],
            ],
        ];
    }

    /**
     * Modell-Output ist nie blind vertrauenswuerdig: alles wird gegen die
     * Heuristik als Default gegengeprueft und auf plausible Werte begrenzt.
     */
    private function normalize(array $payload, string $input, string $timezone): array
    {
        $fallback = $this->heuristic->extract($input, $timezone);
        $now = CarbonImmutable::now($timezone)->startOfDay();

        $range = null;
        if (isset($payload['date_range']['from'], $payload['date_range']['to'])) {
            try {
                $from = CarbonImmutable::parse($payload['date_range']['from'], $timezone)->startOfDay();
                $to = CarbonImmutable::parse($payload['date_range']['to'], $timezone)->startOfDay();

                if ($to->greaterThanOrEqualTo($from)) {
                    $from = $from->lessThan($now) ? $now : $from;
                    $to = $to->greaterThan($from->addWeeks(8)) ? $from->addWeeks(8) : $to;
                    $to = $to->lessThan($from) ? $from : $to;

                    $range = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
                }
            } catch (\Throwable) {
                $range = null;
            }
        }

        $type = $payload['event_type'] ?? $fallback['event_type'];
        $count = isset($payload['participant_count']) ? (int) $payload['participant_count'] : null;

        return [
            'event_name' => trim((string) ($payload['event_name'] ?? '')) ?: $fallback['event_name'],
            'event_type' => $type,
            'participant_count' => ($count && $count > 0 && $count <= 500) ? $count : $fallback['participant_count'],
            'date_range' => $range ?? $fallback['date_range'],
            'preferred_days' => array_values(array_intersect(
                $payload['preferred_days'] ?? [],
                ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
            )) ?: $fallback['preferred_days'],
            'time_of_day' => $payload['time_of_day'] ?? $fallback['time_of_day'],
            'planning_template' => $payload['planning_template']
                ?? config('planning.type_to_template.'.$type, 'generic'),
            'confidence' => array_merge(
                ['event_name' => 'medium', 'participant_count' => 'medium', 'date_range' => 'medium'],
                is_array($payload['confidence'] ?? null) ? $payload['confidence'] : []
            ),
            'source' => 'ai',
        ];
    }
}
