<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Backend-Locale fuer Mails und Planungs-Templates. Die UI-Sprache steuert
 * vue-i18n im Frontend; damit beide zusammenpassen, schickt der Client seine
 * Sprache als Header mit. Ohne Header entscheidet Accept-Language.
 */
class SetLocale
{
    private const SUPPORTED = ['de', 'en', 'fr', 'es', 'nl'];

    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('X-App-Locale')
            ?? $request->getPreferredLanguage(self::SUPPORTED);

        $locale = substr((string) $locale, 0, 2);

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
