<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:update-demo-shows')->dailyAt('00:05')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
Schedule::command('staff:ensure-shifts')->dailyAt('00:10')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
Schedule::command('nosql:neo4j-sync')->hourly()->withoutOverlapping();
Schedule::command('nosql:outbox-replay')->everyMinute()->withoutOverlapping();
