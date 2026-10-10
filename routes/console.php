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
