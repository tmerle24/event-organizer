<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\EventManageController;
use App\Http\Controllers\IcsController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\PlanSectionController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * withViewData setzt den <title> im ausgelieferten HTML. Inertia setzt ihn im
 * Browser noch einmal über <Head> — ein Crawler sieht aber nur das, was der
 * Server schickt.
 */
Route::get('/', fn () => Inertia::render('Landing')
    ->withViewData(['pageTitle' => config('app.name').' – '.config('brand.tagline')]))
    ->name('home');

Route::get('/datenschutz', fn () => Inertia::render('Legal/Privacy')
    ->withViewData(['pageTitle' => 'Datenschutzerklärung – '.config('app.name')]))
    ->name('legal.privacy');

Route::get('/impressum', fn () => Inertia::render('Legal/Imprint')
    ->withViewData(['pageTitle' => 'Impressum – '.config('app.name')]))
    ->name('legal.imprint');

Route::post('/events', [EventController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('events.store');

/*
 * Organisator-Bereich — {event} wird ueber den manage_token aufgeloest
 * (Binding in AppServiceProvider). Alle Endpunkte antworten mit JSON.
 */
Route::prefix('/e/{event}')->name('manage.')->group(function () {
    Route::get('/', [EventManageController::class, 'show'])->name('show');
    Route::get('/data', [EventManageController::class, 'data'])->name('data');
    Route::patch('/', [EventManageController::class, 'update'])->name('update');
    Route::delete('/', [EventManageController::class, 'destroy'])->name('destroy');

    Route::post('/options', [EventManageController::class, 'storeOption'])->name('options.store');
    Route::patch('/options/{option}', [EventManageController::class, 'updateOption'])->name('options.update');
    Route::delete('/options/{option}', [EventManageController::class, 'destroyOption'])->name('options.destroy');
    Route::post('/options/suggest', [EventManageController::class, 'suggestOptions'])->name('options.suggest');

    Route::post('/decide', [EventManageController::class, 'decide'])->name('decide');
    Route::post('/undecide', [EventManageController::class, 'undecide'])->name('undecide');
    Route::post('/cancel', [EventManageController::class, 'cancel'])->name('cancel');
    Route::post('/reopen', [EventManageController::class, 'reopen'])->name('reopen');

    Route::patch('/participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
    Route::delete('/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');
    Route::post('/participants/{participant}/merge', [ParticipantController::class, 'merge'])->name('participants.merge');

    Route::post('/sections', [PlanSectionController::class, 'store'])->name('sections.store');
    Route::patch('/sections/{section}', [PlanSectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/{section}', [PlanSectionController::class, 'destroy'])->name('sections.destroy');

    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/adopt', [TaskController::class, 'adopt'])->name('tasks.adopt');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::post('/invite', [EventManageController::class, 'invite'])
        ->middleware('throttle:10,1')->name('invite');
    Route::post('/email', [EventManageController::class, 'sendManageLink'])
        ->middleware('throttle:5,1')->name('email');

    Route::get('/event.ics', [IcsController::class, 'download'])->name('ics');
});

/*
 * Teilnehmer-Bereich — {event} wird ueber den public_token aufgeloest.
 * Der manage_token darf hier unter keinen Umstaenden auftauchen.
 */
Route::prefix('/t/{event}')->name('public.')->group(function () {
    Route::get('/', [PublicEventController::class, 'show'])->name('show');
    Route::get('/state', [PublicEventController::class, 'state'])->name('state');

    Route::post('/join', [PublicEventController::class, 'join'])
        ->middleware('throttle:20,1')->name('join');
    Route::post('/availability', [PublicEventController::class, 'storeAvailability'])
        ->middleware('throttle:60,1')->name('availability');
    Route::post('/leave', [PublicEventController::class, 'leave'])
        ->middleware('throttle:10,1')->name('leave');

    Route::post('/tasks', [TaskController::class, 'store'])
        ->middleware('throttle:30,1')->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])
        ->middleware('throttle:60,1')->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])
        ->middleware('throttle:30,1')->name('tasks.destroy');

    Route::get('/event.ics', [IcsController::class, 'download'])->name('ics');
});
