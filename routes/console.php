<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Newsletter automatique : résumé hebdomadaire des derniers articles publiés (lundi 8h)
Schedule::command('app:send-newsletter-digest')
    ->weeklyOn(1, '08:00')
    ->onOneServer();

// Évite l'accumulation des sessions techniques utilisées pour qualifier une écoute.
Schedule::command('model:prune')
    ->dailyAt('02:20')
    ->onOneServer()
    ->withoutOverlapping();
