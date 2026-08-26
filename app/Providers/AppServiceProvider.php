<?php

namespace App\Providers;

use App\Models\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        /*
         * Ein einziger {event}-Parameter, zwei Schluessel: in manage.*-Routen
         * wird ueber manage_token aufgeloest, in public.*-Routen ueber
         * public_token. Damit kann eine Public-URL niemals versehentlich in
         * den Organisator-Bereich fuehren.
         */
        Route::bind('event', function ($value, $route) {
            $column = str_starts_with($route->getName() ?? '', 'public.')
                ? 'public_token'
                : 'manage_token';

            return Event::where($column, $value)->firstOrFail();
        });
    }
}
