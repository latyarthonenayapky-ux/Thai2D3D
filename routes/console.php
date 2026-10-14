<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rounds:sync-schedule')
    ->everyMinute()
    ->timezone('Asia/Yangon')
    ->withoutOverlapping();

Schedule::command('users:expire-temp')
    ->hourly()
    ->timezone('Asia/Yangon')
    ->withoutOverlapping();

// Keeps the licence clock reference moving on a desktop that is left open, and
// reports LICENCE-STATUS for the launcher log.
Schedule::command('thai2d3d:licence-check')
    ->daily()
    ->timezone('Asia/Yangon')
    ->withoutOverlapping();
