<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('snapshot:capture-shift-boundaries')->everyMinute()->withoutOverlapping();
Schedule::command('snapshot:sweep-unfinalized')->everyFifteenMinutes()->withoutOverlapping();
