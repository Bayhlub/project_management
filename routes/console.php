<?php

use Illuminate\Support\Facades\Schedule;

// Task alarms. Locally run `php artisan schedule:work` to keep this going;
// on a server add the single cron entry from the Laravel docs.
Schedule::command('tasks:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();
