<?php

use App\Services\OutreachQueue;
use App\Services\ReplyInbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('outreach:send', function (OutreachQueue $queue) {
    $this->info('Queued '.$queue->queueApprovedSends().' approved emails for sending.');
})->purpose('Send approved emails that are still waiting, up to the daily limit');

Artisan::command('outreach:fetch-replies', function (ReplyInbox $inbox) {
    $this->info('Saved '.$inbox->fetch().' new replies.');
})->purpose('Check the mailbox for replies to sent outreach emails');

// Both need `php artisan schedule:work` running.
// Approved emails held back by yesterday's limit go out each morning.
Schedule::command('outreach:send')->dailyAt('10:00');
Schedule::command('outreach:fetch-replies')->everyFiveMinutes()->withoutOverlapping();

// Shared hosting has no always-on worker: cron runs the queue every minute instead.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
