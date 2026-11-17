<?php

namespace App\Console\Commands;

use App\Actions\SendDueTaskReminders;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Send reminders for tasks whose reminder time has come';

    public function handle(SendDueTaskReminders $sender): int
    {
        $sent = $sender->handle();

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
