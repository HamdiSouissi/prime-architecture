<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('planning:remind')->dailyAt((string) config('planning.daily_reminder_time'))->timezone((string) config('planning.timezone'))->withoutOverlapping();
Schedule::command('billing:update-statuses')->dailyAt('00:10')->timezone((string) config('app.timezone'))->withoutOverlapping();
