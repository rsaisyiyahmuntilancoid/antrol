<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
// use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Schedule::command('bpjs:sync-patient-visits --date=yesterday')
//     ->dailyAt('01:00')
//     ->withoutOverlapping();

// Schedule::command('bpjs:sync-patient-visits')
//     ->everyThirtyMinutes()
//     ->withoutOverlapping()
//     ->between('06:00', '22:00');
