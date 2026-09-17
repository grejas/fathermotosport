<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Genera la siguiente ocurrencia de las promociones flash recurrentes.
// Cada 10 min basta: generamos la SIGUIENTE ocurrencia con anticipación, no en tiempo real.
Schedule::command('flash-promos:generate-next-occurrences')
    ->everyTenMinutes()
    ->withoutOverlapping();
