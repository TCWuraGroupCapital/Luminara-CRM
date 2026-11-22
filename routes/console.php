<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Webkul\Email\Support\BusinessInbox;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inbound-emails:process')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->when(fn () => config('mail-receiver.default') === 'webklex-imap' || app(BusinessInbox::class)->hasOwnAccount());

Schedule::command('luminara:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping(30);

Schedule::command('luminara:backup-verify')
    ->dailyAt('02:30')
    ->withoutOverlapping(15);

Schedule::command('luminara:ledger-seal')
    ->hourly()
    ->withoutOverlapping(10);

Schedule::command('luminara:queue-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('luminara:ops-alert')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
