<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run --class=daily')->dailyAt('01:00')->timezone('Asia/Manila')->withoutOverlapping(180);
Schedule::command('backup:run --class=weekly')->weeklyOn(1, '02:00')->timezone('Asia/Manila')->withoutOverlapping(240);
Schedule::command('backup:run --class=monthly')->monthlyOn(1, '03:00')->timezone('Asia/Manila')->withoutOverlapping(300);
